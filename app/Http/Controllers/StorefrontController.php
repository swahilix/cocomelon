<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\ProductReel;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Store/Home', [
            'heroSlides' => $this->heroSlides(),
            'productReels' => $this->productReels(),
            'featuredProducts' => Product::with(['brand', 'category'])
                ->active()
                ->where('featured', true)
                ->orderBy('sort_order')
                ->latest()
                ->take(8)
                ->get(),
            'productCategories' => Category::productType()
                ->active()
                ->withCount(['products' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take(8)
                ->get(),
            'serviceCategories' => Category::serviceType()
                ->active()
                ->withCount(['services' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take(6)
                ->get(),
            'brands' => Brand::where('is_active', true)->orderBy('name')->take(12)->get(),
        ]);
    }

    public function catalog(Request $request): Response
    {
        return $this->renderCatalog(
            $request,
            $this->categoryFromQuery($request, 'product')
        );
    }

    public function productCategory(Request $request, string $category): Response
    {
        return $this->renderCatalog(
            $request,
            $this->findActiveCategory($category, 'product')
        );
    }

    public function product(Product $product): Response
    {
        abort_unless($product->status === 'active', 404);
        $product->load(['brand', 'category']);

        return Inertia::render('Store/Product', [
            'product' => $product,
            'related' => Product::with(['brand', 'category'])
                ->active()
                ->whereKeyNot($product->id)
                ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
                ->take(4)
                ->get(),
        ]);
    }

    public function services(Request $request): Response
    {
        return $this->renderServices(
            $request,
            $this->categoryFromQuery($request, 'service')
        );
    }

    public function serviceCategory(Request $request, string $category): Response
    {
        return $this->renderServices(
            $request,
            $this->findActiveCategory($category, 'service')
        );
    }

    public function service(Service $service): Response
    {
        abort_unless($service->status === 'active', 404);

        return Inertia::render('Store/Service', [
            'service' => $service->load('category'),
        ]);
    }

    private function productReels(): array
    {
        return ProductReel::query()
            ->active()
            ->with(['product.brand', 'product.category', 'service.category'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->take(20)
            ->get()
            ->map(function (ProductReel $reel): ?array {
                $item = $reel->item_type === 'service' ? $reel->service : $reel->product;

                if (! $item || $item->status !== 'active') {
                    return null;
                }

                $isProduct = $reel->item_type === 'product';

                return [
                    'id' => $reel->id,
                    'item_type' => $reel->item_type,
                    'title' => $reel->title ?: $item->name,
                    'caption' => $reel->caption ?: $item->short_description,
                    'media_type' => $reel->media_type,
                    'media_url' => $reel->media_url,
                    'cover_url' => $reel->cover_url,
                    'item_url' => $reel->item_url,
                    'cta_label' => $reel->cta_label ?: ($isProduct ? 'View product' : 'View service'),
                    'display_seconds' => $reel->display_seconds,
                    'item' => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'slug' => $item->slug,
                        'image_url' => $item->image_url,
                        'category' => $item->category ? ['name' => $item->category->name] : null,
                        'brand' => $isProduct && $item->brand ? ['name' => $item->brand->name] : null,
                        'current_price' => $isProduct ? $item->current_price : null,
                        'price_label' => $isProduct ? null : ($item->price_label ?: ($item->price_from ? 'From TZS '.number_format((float) $item->price_from, 0) : 'Request a quote')),
                        'in_stock' => $isProduct ? $item->in_stock : null,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function heroSlides(): array
    {
        return HeroSlide::query()
            ->active()
            ->where('item_type', 'image')
            ->whereNotNull('image_path')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HeroSlide $slide): array => [
                'id' => $slide->id,
                'headline' => $slide->headline,
                'image_url' => $slide->image_url,
                'image_alt' => $slide->image_alt,
                'link_url' => $slide->link_url,
            ])
            ->values()
            ->all();
    }

    private function categoryFromQuery(Request $request, string $type): ?Category
    {
        $slug = trim($request->string('category')->toString());

        if ($slug === '') {
            return null;
        }

        return Category::query()
            ->where('type', $type)
            ->active()
            ->where('slug', $slug)
            ->first();
    }

    private function findActiveCategory(string $value, string $type): Category
    {
        return Category::query()
            ->where('type', $type)
            ->active()
            ->where(function ($query) use ($value): void {
                $query->where('slug', $value);

                if (ctype_digit($value)) {
                    $query->orWhereKey((int) $value);
                }
            })
            ->firstOrFail();
    }

    private function renderCatalog(Request $request, ?Category $selectedCategory = null): Response
    {
        $categorySlug = $selectedCategory?->slug ?: $request->string('category')->toString();
        $products = Product::with(['brand', 'category'])->active()
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('sku', 'like', '%'.$request->search.'%')
                ->orWhere('description', 'like', '%'.$request->search.'%')))
            ->when($categorySlug, fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $categorySlug)->where('type', 'product')->where('is_active', true)))
            ->when($request->filled('brand'), fn ($query) => $query->whereHas('brand', fn ($brand) => $brand->where('slug', $request->brand)))
            ->when($request->boolean('in_stock'), fn ($query) => $query->where(fn ($stock) => $stock->where('track_stock', false)->orWhere('stock_quantity', '>', 0)))
            ->when($request->filled('min_price'), fn ($query) => $query->where('price', '>=', (float) $request->min_price))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price', '<=', (float) $request->max_price));

        match ($request->input('sort')) {
            'price_low' => $products->orderBy('price'),
            'price_high' => $products->orderByDesc('price'),
            'name' => $products->orderBy('name'),
            'newest' => $products->latest(),
            default => $products->orderByDesc('featured')->orderBy('sort_order')->latest(),
        };

        return Inertia::render('Store/Catalog', [
            'products' => $products->paginate(16)->withQueryString(),
            'categories' => Category::productType()
                ->active()
                ->withCount(['products' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'selectedCategory' => $selectedCategory,
            'filters' => [
                ...$request->only('search', 'brand', 'in_stock', 'min_price', 'max_price', 'sort'),
                'category' => $categorySlug,
            ],
        ]);
    }

    private function renderServices(Request $request, ?Category $selectedCategory = null): Response
    {
        $categorySlug = $selectedCategory?->slug ?: $request->string('category')->toString();
        $services = Service::with('category')->active()
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('short_description', 'like', '%'.$request->search.'%')
                ->orWhere('description', 'like', '%'.$request->search.'%')))
            ->when($categorySlug, fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $categorySlug)->where('type', 'service')->where('is_active', true)))
            ->orderByDesc('featured')
            ->orderBy('sort_order')
            ->orderBy('name');

        return Inertia::render('Store/Services', [
            'services' => $services->paginate(12)->withQueryString(),
            'categories' => Category::serviceType()
                ->active()
                ->withCount(['services' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'selectedCategory' => $selectedCategory,
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $categorySlug,
            ],
        ]);
    }
}
