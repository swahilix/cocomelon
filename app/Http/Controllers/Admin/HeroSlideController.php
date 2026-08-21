<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HeroSlideController extends Controller
{
    public function index(Request $request): Response
    {
        $showTrash = $request->input('view') === 'trash';
        $query = $showTrash ? HeroSlide::onlyTrashed() : HeroSlide::query();

        return Inertia::render('Admin/Carousel/Index', [
            'slides' => $query
                ->with(['product.brand', 'product.category', 'service.category'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'products' => Product::query()
                ->with(['brand:id,name', 'category:id,name'])
                ->active()
                ->orderByDesc('featured')
                ->orderBy('name')
                ->get(['id', 'category_id', 'brand_id', 'name', 'slug', 'short_description', 'description', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'primary_image', 'stock_quantity', 'track_stock', 'warranty']),
            'services' => Service::query()
                ->with('category:id,name')
                ->active()
                ->orderByDesc('featured')
                ->orderBy('name')
                ->get(['id', 'category_id', 'name', 'slug', 'short_description', 'description', 'price_from', 'price_label', 'duration', 'image']),
            'view' => $showTrash ? 'trash' : 'active',
            'counts' => [
                'active' => HeroSlide::count(),
                'published' => HeroSlide::active()->count(),
                'trash' => HeroSlide::onlyTrashed()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureItemIsNotAlreadyUsed($data);
        HeroSlide::create($data);

        return back()->with('success', 'Carousel slide created successfully.');
    }

    public function update(Request $request, HeroSlide $heroSlide): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureItemIsNotAlreadyUsed($data, $heroSlide);
        $heroSlide->update($data);

        return back()->with('success', 'Carousel slide updated successfully.');
    }

    public function destroy(HeroSlide $heroSlide): RedirectResponse
    {
        $heroSlide->delete();

        return back()->with('success', 'Carousel slide moved to Trash.');
    }

    public function restore(int $heroSlide): RedirectResponse
    {
        HeroSlide::onlyTrashed()->findOrFail($heroSlide)->restore();

        return back()->with('success', 'Carousel slide restored successfully.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'item_type' => ['required', Rule::in(['product', 'service'])],
            'item_id' => ['required', 'integer', 'min:1'],
            'eyebrow' => ['nullable', 'string', 'max:100'],
            'badge' => ['nullable', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:600'],
            'primary_label' => ['nullable', 'string', 'max:60'],
            'secondary_label' => ['nullable', 'string', 'max:60'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

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

        unset($data['item_id']);

        foreach (['eyebrow', 'badge', 'headline', 'description', 'primary_label', 'secondary_label'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }

        return $data;
    }

    private function ensureItemIsNotAlreadyUsed(array $data, ?HeroSlide $current = null): void
    {
        $column = $data['item_type'] === 'product' ? 'product_id' : 'service_id';
        $itemId = $data[$column];

        $exists = HeroSlide::query()
            ->where($column, $itemId)
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'item_id' => 'This item is already included in the homepage carousel.',
            ]);
        }
    }
}
