<?php

namespace App\Http\Controllers;

use App\Models\BuyingGuide;
use App\Models\Product;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BuyingGuideController extends Controller
{
    public function index(): Response
    {
        $guides = BuyingGuide::query()
            ->published()
            ->with('category:id,name,slug')
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->paginate(12);

        return Inertia::render('Store/Guides/Index', [
            'guides' => $guides,
        ]);
    }

    public function show(BuyingGuide $buyingGuide): Response
    {
        abort_unless(
            $buyingGuide->status === 'published'
            && $buyingGuide->published_at
            && $buyingGuide->published_at->lte(now()),
            404
        );

        $buyingGuide->load('category:id,name,slug');

        $ids = collect($buyingGuide->related_product_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $rank = array_flip($ids->all());

        $relatedProducts = $ids->isEmpty()
            ? collect()
            : Product::query()
                ->with(['brand:id,name', 'category:id,name,slug'])
                ->withCount(['variants as active_variants_count' => fn ($query) => $query->active()])
                ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->approved()])
                ->withAvg(['reviews as approved_reviews_average' => fn ($query) => $query->approved()], 'rating')
                ->active()
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn (Product $product) => $rank[$product->id] ?? PHP_INT_MAX)
                ->values();

        $relatedGuides = BuyingGuide::query()
            ->published()
            ->with('category:id,name,slug')
            ->whereKeyNot($buyingGuide->id)
            ->when(
                $buyingGuide->category_id,
                fn ($query) => $query->where('category_id', $buyingGuide->category_id)
            )
            ->latest('published_at')
            ->take(3)
            ->get();

        return Inertia::render('Store/Guides/Show', [
            'guide' => $buyingGuide,
            'relatedProducts' => $relatedProducts,
            'relatedGuides' => $relatedGuides,
        ]);
    }
}
