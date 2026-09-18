<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReel;
use App\Models\ProductReview;
use App\Models\Service;
use App\Services\ImageOptimizationService;
use App\Services\ProductActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly ImageOptimizationService $images,
        private readonly ProductActivityService $activity,
    ) {
    }

    public function home(): Response
    {
        $newArrivals = $this->productCardsQuery()
            ->latest('products.created_at')
            ->take(5)
            ->get();

        $dailyDeals = $this->productCardsQuery()
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->whereColumn('sale_price', '<', 'price')
            ->where(fn ($query) => $query->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', now()))
            ->orderByRaw('CASE WHEN sale_ends_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sale_ends_at')
            ->take(5)
            ->get();

        $bestSellerRows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('order_items.product_id')
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->whereNotNull('order_items.product_id')
            ->where('orders.status', 'completed')
            ->groupBy('order_items.product_id')
            ->orderByDesc('units_sold')
            ->take(5)
            ->get();

        $bestSellers = $this->rankedProducts(
            $bestSellerRows->pluck('product_id')->map(fn ($id) => (int) $id)->all(),
            $bestSellerRows->pluck('units_sold', 'product_id')->map(fn ($value) => (int) $value)->all(),
            'units_sold',
        );

        $trendingScores = collect();

        if (Schema::hasTable('product_activity_events')) {
            $trendingScores = DB::table('product_activity_events')
                ->select('product_id')
                ->selectRaw("SUM(CASE WHEN event_type = 'cart_add' THEN 3 ELSE 1 END) as score")
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('product_id')
                ->pluck('score', 'product_id')
                ->map(fn ($value) => (int) $value);
        }

        if (Schema::hasTable('product_contact_events')) {
            DB::table('product_contact_events')
                ->select('product_id')
                ->selectRaw('COUNT(*) as enquiries')
                ->where('channel', 'whatsapp')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('product_id')
                ->pluck('enquiries', 'product_id')
                ->each(function ($count, $productId) use (&$trendingScores): void {
                    $trendingScores->put(
                        $productId,
                        (int) $trendingScores->get($productId, 0) + ((int) $count * 4),
                    );
                });
        }

        $trendingScores = $trendingScores->sortDesc()->take(5);
        $trending = $this->rankedProducts(
            $trendingScores->keys()->map(fn ($id) => (int) $id)->all(),
            $trendingScores->all(),
            'trend_score',
        );

        return Inertia::render('Store/Home', [
            'heroSlides' => $this->heroSlides(),
            'productReels' => $this->productReels(),
            'newArrivals' => $this->homeProductPayload($newArrivals),
            'bestSellers' => $this->homeProductPayload($bestSellers),
            'trendingProducts' => $this->homeProductPayload($trending),
            'dailyDeals' => $this->homeProductPayload($dailyDeals),
            'productCategories' => Category::productType()
                ->active()
                ->withCount(['products' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take(8)
                ->get()
                ->map(fn (Category $category): array => [
                    ...$category->toArray(),
                    'image_srcset' => $this->images->responsiveWebpSrcset($category->image, [160, 240]),
                ])
                ->values()
                ->all(),
            'serviceCategories' => Category::serviceType()
                ->active()
                ->withCount(['services' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take(6)
                ->get(),
            'brands' => Brand::where('is_active', true)
                ->orderBy('name')
                ->take(12)
                ->get()
                ->map(fn (Brand $brand): array => [
                    ...$brand->toArray(),
                    'logo_srcset' => $this->images->responsiveWebpSrcset($brand->logo, [160, 240]),
                ])
                ->values()
                ->all(),
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

    public function product(Request $request, Product $product): Response
    {
        abort_unless($product->status === 'active', 404);
        $this->activity->record($request, $product, 'view');
        $product->load(['brand', 'category']);

        $approvedReviews = ProductReview::query()
            ->approved()
            ->where('product_id', $product->id)
            ->latest('approved_at')
            ->latest('id')
            ->take(30)
            ->get();

        $reviewSummary = ProductReview::query()
            ->approved()
            ->where('product_id', $product->id)
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as review_average')
            ->first();

        $reviewEligibility = [
            'can_review' => false,
            'reason' => 'Sign in and complete a purchase of this product to leave a verified review.',
            'review' => null,
        ];

        if ($request->user() && ! $request->user()->is_admin) {
            $eligibleOrder = Order::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'completed')
                ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))
                ->latest('id')
                ->first();

            $existingReview = ProductReview::query()
                ->where('product_id', $product->id)
                ->where('user_id', $request->user()->id)
                ->first();

            $reviewEligibility = [
                'can_review' => (bool) $eligibleOrder,
                'reason' => $eligibleOrder ? null : 'Verified reviews are available after an order containing this product is completed.',
                'review' => $existingReview ? [
                    'rating' => $existingReview->rating,
                    'title' => $existingReview->title,
                    'body' => $existingReview->body,
                    'status' => $existingReview->status,
                ] : null,
            ];
        }

        return Inertia::render('Store/Product', [
            'product' => $product,
            'reviews' => $approvedReviews->map(fn (ProductReview $review): array => [
                'id' => $review->id,
                'reviewer_name' => $review->reviewer_name,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'is_verified_purchase' => $review->is_verified_purchase,
                'created_at' => $review->created_at?->toDateString(),
            ])->values()->all(),
            'reviewSummary' => [
                'count' => (int) ($reviewSummary?->review_count ?? 0),
                'average' => round((float) ($reviewSummary?->review_average ?? 0), 1),
            ],
            'reviewEligibility' => $reviewEligibility,
            'related' => Product::with(['brand', 'category'])
                ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->approved()])
                ->withAvg(['reviews as approved_reviews_average' => fn ($query) => $query->approved()], 'rating')
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

    private function productCardsQuery()
    {
        return Product::query()
            ->with(['brand', 'category'])
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->approved()])
            ->withAvg(['reviews as approved_reviews_average' => fn ($query) => $query->approved()], 'rating')
            ->active()
            ->where(fn ($query) => $query->where('track_stock', false)->orWhere('stock_quantity', '>', 0));
    }

    private function rankedProducts(array $productIds, array $metrics, string $metricKey): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $rank = array_flip($productIds);

        return $this->productCardsQuery()
            ->whereIn('products.id', $productIds)
            ->get()
            ->sortBy(fn (Product $product) => $rank[$product->id] ?? PHP_INT_MAX)
            ->values()
            ->each(function (Product $product) use ($metrics, $metricKey): void {
                $product->setAttribute($metricKey, (int) ($metrics[$product->id] ?? 0));
            });
    }

    private function homeProductPayload(Collection $products): array
    {
        return $products
            ->map(fn (Product $product): array => [
                ...$product->toArray(),
                'image_srcset' => $this->images->responsiveWebpSrcset($product->primary_image, [240, 360, 480]),
            ])
            ->values()
            ->all();
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
                'image_srcset' => $this->images->responsiveWebpSrcset($slide->image_path, [720, 960, 1280, 1600]),
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
        $products = Product::with(['brand', 'category'])
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->approved()])
            ->withAvg(['reviews as approved_reviews_average' => fn ($query) => $query->approved()], 'rating')
            ->active()
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
            'products' => $products->paginate(10)->withQueryString(),
            'categories' => Category::productType()
                ->active()
                ->withCount(['products' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'brands' => Brand::query()
                ->select(['id', 'name', 'slug'])
                ->where('is_active', true)
                ->withCount(['products' => fn ($query) => $query->active()])
                ->orderBy('name')
                ->get(),
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
