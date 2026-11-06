<?php

namespace App\Http\Controllers;

use App\Models\BuyingGuide;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class LocalStoreController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Store/LocalStore', [
            'categories' => Category::query()
                ->productType()
                ->active()
                ->withCount(['products' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take(8)
                ->get(),
            'products' => Product::query()
                ->with(['brand:id,name', 'category:id,name,slug'])
                ->withCount(['variants as active_variants_count' => fn ($query) => $query->active()])
                ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->approved()])
                ->withAvg(['reviews as approved_reviews_average' => fn ($query) => $query->approved()], 'rating')
                ->active()
                ->where(fn ($query) => $query->where('track_stock', false)->orWhere('stock_quantity', '>', 0))
                ->latest()
                ->take(8)
                ->get(),
            'guides' => BuyingGuide::query()
                ->published()
                ->with('category:id,name,slug')
                ->latest('published_at')
                ->take(4)
                ->get(),
        ]);
    }
}
