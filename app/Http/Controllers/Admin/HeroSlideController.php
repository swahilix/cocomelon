<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Services\ImageOptimizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HeroSlideController extends Controller
{
    public function __construct(private readonly ImageOptimizationService $images)
    {
    }

    public function index(Request $request): Response
    {
        $showTrash = $request->input('view') === 'trash';
        $query = $showTrash ? HeroSlide::onlyTrashed() : HeroSlide::query();

        return Inertia::render('Admin/Carousel/Index', [
            'slides' => $query
                ->where('item_type', 'image')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'view' => $showTrash ? 'trash' : 'active',
            'counts' => [
                'active' => HeroSlide::where('item_type', 'image')->count(),
                'published' => HeroSlide::where('item_type', 'image')->active()->count(),
                'trash' => HeroSlide::onlyTrashed()->where('item_type', 'image')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        if (! $request->hasFile('image')) {
            throw ValidationException::withMessages([
                'image' => 'Upload a banner image for this carousel slide.',
            ]);
        }

        $data = $this->handleImage($request, $data);
        HeroSlide::create($data);

        return back()->with('success', 'Carousel banner created successfully.');
    }

    public function update(Request $request, HeroSlide $heroSlide): RedirectResponse
    {
        abort_unless($heroSlide->item_type === 'image', 404);

        $data = $this->validatedData($request);
        $data = $this->handleImage($request, $data, $heroSlide);
        $heroSlide->update($data);

        return back()->with('success', 'Carousel banner updated successfully.');
    }

    public function destroy(HeroSlide $heroSlide): RedirectResponse
    {
        abort_unless($heroSlide->item_type === 'image', 404);
        $heroSlide->delete();

        return back()->with('success', 'Carousel banner moved to Trash.');
    }

    public function restore(int $heroSlide): RedirectResponse
    {
        HeroSlide::onlyTrashed()
            ->where('item_type', 'image')
            ->findOrFail($heroSlide)
            ->restore();

        return back()->with('success', 'Carousel banner restored successfully.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288', 'dimensions:min_width=1000,min_height=280'],
            'headline' => ['nullable', 'string', 'max:191'],
            'image_alt' => ['nullable', 'string', 'max:191'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        unset($data['image']);

        $data['item_type'] = 'image';
        $data['product_id'] = null;
        $data['service_id'] = null;
        $data['headline'] = filled($data['headline'] ?? null) ? trim((string) $data['headline']) : null;
        $data['image_alt'] = filled($data['image_alt'] ?? null) ? trim((string) $data['image_alt']) : null;
        $data['link_url'] = $this->normalizeLink($data['link_url'] ?? null);

        return $data;
    }

    private function handleImage(Request $request, array $data, ?HeroSlide $slide = null): array
    {
        if (! $request->hasFile('image')) {
            return $data;
        }

        try {
            $path = $this->images->store(
                $request->file('image'),
                'hero-slides',
                $data['headline'] ?: 'homepage-banner-'.($data['sort_order'] ?? 0),
                [
                    'title' => $data['headline'] ?: 'Prinx homepage banner',
                    'description' => $data['image_alt'] ?: $data['headline'] ?: 'Prinx Electronics homepage banner',
                    'keywords' => ['Prinx Electronics', 'homepage banner', 'electronics'],
                ],
            );
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'image' => $exception->getMessage(),
            ]);
        }

        $this->images->deleteLocal($slide?->image_path);
        $data['image_path'] = $path;
        $this->images->ensureResponsiveWebpVariants($path, [720, 960, 1280, 1600]);

        return $data;
    }

    private function normalizeLink(?string $value): ?string
    {
        $link = trim((string) $value);

        if ($link === '') {
            return null;
        }

        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return $link;
        }

        if (filter_var($link, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
            if (in_array($scheme, ['http', 'https'], true)) {
                return $link;
            }
        }

        throw ValidationException::withMessages([
            'link_url' => 'Use a site path such as /shop or a complete http/https URL.',
        ]);
    }
}
