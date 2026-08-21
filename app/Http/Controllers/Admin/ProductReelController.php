<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReel;
use App\Models\Service;
use App\Services\ImageOptimizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProductReelController extends Controller
{
    public function __construct(private readonly ImageOptimizationService $images)
    {
    }

    public function index(Request $request): Response
    {
        $showTrash = $request->input('view') === 'trash';
        $query = $showTrash ? ProductReel::onlyTrashed() : ProductReel::query();

        return Inertia::render('Admin/Reels/Index', [
            'reels' => $query
                ->with(['product.brand', 'product.category', 'service.category'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'products' => Product::query()
                ->with(['brand:id,name', 'category:id,name'])
                ->active()
                ->orderByDesc('featured')
                ->orderBy('name')
                ->get(['id', 'category_id', 'brand_id', 'name', 'slug', 'short_description', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'primary_image', 'stock_quantity', 'track_stock']),
            'services' => Service::query()
                ->with('category:id,name')
                ->active()
                ->orderByDesc('featured')
                ->orderBy('name')
                ->get(['id', 'category_id', 'name', 'slug', 'short_description', 'price_from', 'price_label', 'duration', 'image']),
            'view' => $showTrash ? 'trash' : 'active',
            'counts' => [
                'active' => ProductReel::count(),
                'published' => ProductReel::active()->count(),
                'trash' => ProductReel::onlyTrashed()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->handleMedia($request, $this->validatedData($request));
        ProductReel::create($data);

        return back()->with('success', 'Product reel created successfully.');
    }

    public function update(Request $request, ProductReel $productReel): RedirectResponse
    {
        $data = $this->handleMedia($request, $this->validatedData($request, $productReel), $productReel);
        $productReel->update($data);

        return back()->with('success', 'Product reel updated successfully.');
    }

    public function destroy(ProductReel $productReel): RedirectResponse
    {
        $productReel->delete();

        return back()->with('success', 'Product reel moved to Trash.');
    }

    public function restore(int $productReel): RedirectResponse
    {
        ProductReel::onlyTrashed()->findOrFail($productReel)->restore();

        return back()->with('success', 'Product reel restored successfully.');
    }

    private function validatedData(Request $request, ?ProductReel $current = null): array
    {
        $mediaRules = ['nullable', 'file', 'max:51200'];
        $mediaRules[] = $request->input('media_type') === 'video'
            ? 'mimes:mp4,webm,mov'
            : 'mimes:jpg,jpeg,png,webp';

        if ($request->input('media_type') !== 'video') {
            $mediaRules[] = 'dimensions:min_width=540,min_height=720';
        }

        $data = $request->validate([
            'item_type' => ['required', Rule::in(['product', 'service'])],
            'item_id' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:160'],
            'caption' => ['nullable', 'string', 'max:800'],
            'media_type' => ['required', Rule::in(['image', 'video'])],
            'media_file' => $mediaRules,
            'external_url' => ['nullable', 'url', 'max:2048'],
            'cover_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=540,min_height=720'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'display_seconds' => ['required', 'integer', 'min:3', 'max:15'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $hasExistingVideo = $current
            && $current->media_type === 'video'
            && (filled($current->media_path) || filled($current->external_url));

        if ($data['media_type'] === 'video'
            && ! $request->hasFile('media_file')
            && blank($data['external_url'] ?? null)
            && ! $hasExistingVideo) {
            throw ValidationException::withMessages([
                'media_file' => 'Upload a video or provide a direct video URL.',
            ]);
        }

        $itemId = (int) $data['item_id'];

        if ($data['item_type'] === 'product') {
            Product::query()->active()->findOrFail($itemId);
            $data['product_id'] = $itemId;
            $data['service_id'] = null;
        } else {
            Service::query()->active()->findOrFail($itemId);
            $data['service_id'] = $itemId;
            $data['product_id'] = null;
        }

        unset($data['item_id'], $data['media_file'], $data['cover_image_file']);

        foreach (['title', 'caption', 'external_url', 'cta_label'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }

        return $data;
    }

    private function handleMedia(Request $request, array $data, ?ProductReel $reel = null): array
    {
        $oldMediaPath = $reel?->media_path;
        $oldCoverPath = $reel?->cover_image;
        $item = $data['item_type'] === 'product'
            ? Product::with(['brand', 'category'])->find($data['product_id'])
            : Service::with('category')->find($data['service_id']);

        if ($request->hasFile('media_file')) {
            try {
                if ($data['media_type'] === 'image') {
                    $data['media_path'] = $this->images->store(
                        $request->file('media_file'),
                        'reels/images',
                        ($data['title'] ?: $item?->name ?: 'product reel').' vertical reel',
                        $this->imageMetadata($data, $item)
                    );
                } else {
                    $data['media_path'] = $this->storeVideo($request->file('media_file'), $data['title'] ?: $item?->name ?: 'product-reel');
                }
            } catch (Throwable $exception) {
                throw ValidationException::withMessages(['media_file' => $exception->getMessage()]);
            }

            $data['external_url'] = null;
            $this->deleteLocalMedia($oldMediaPath);
        } elseif (filled($data['external_url'] ?? null)) {
            $data['media_path'] = null;
            $this->deleteLocalMedia($oldMediaPath);
        } elseif ($reel && $reel->media_type !== $data['media_type']) {
            $data['media_path'] = null;
            $data['external_url'] = null;
            $this->deleteLocalMedia($oldMediaPath);
        } else {
            unset($data['media_path']);
        }

        if ($request->hasFile('cover_image_file')) {
            try {
                $data['cover_image'] = $this->images->store(
                    $request->file('cover_image_file'),
                    'reels/covers',
                    ($data['title'] ?: $item?->name ?: 'product reel').' cover',
                    $this->imageMetadata($data, $item)
                );
            } catch (Throwable $exception) {
                throw ValidationException::withMessages(['cover_image_file' => $exception->getMessage()]);
            }

            $this->images->deleteLocal($oldCoverPath);
        } else {
            unset($data['cover_image']);
        }

        return $data;
    }

    private function storeVideo($file, string $baseName): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
        $filename = Str::limit(Str::slug($baseName) ?: 'product-reel', 80, '').'-'.Str::lower(Str::random(8)).'.'.$extension;

        $path = $file->storeAs('reels/videos', $filename, config('media.disk', 'public'));

        if (! $path) {
            throw new \RuntimeException('The reel video could not be stored. Check storage permissions and available disk space.');
        }

        return $path;
    }

    private function deleteLocalMedia(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://', '/'])) {
            return;
        }

        Storage::disk(config('media.disk', 'public'))->delete($path);
    }

    private function imageMetadata(array $data, $item): array
    {
        $category = $item?->category?->name;
        $brand = $item?->brand?->name;
        $description = trim((string) ($data['caption'] ?? $item?->short_description ?? ''));

        return [
            'title' => ($data['title'] ?: $item?->name ?: 'Product reel').' | Prinx Electronics',
            'description' => $description,
            'keywords' => array_values(array_filter([
                $item?->name,
                $brand,
                $category,
                'product reel',
                'electronics',
                'Prinx Electronics',
                'Tanzania',
            ])),
        ];
    }
}
