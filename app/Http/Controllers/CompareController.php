<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompareController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $slugs = collect(explode(',', (string) $request->query('products', '')))
            ->map(fn ($slug) => trim($slug))
            ->filter()
            ->unique()
            ->take(4)
            ->values();

        $rank = array_flip($slugs->all());

        $products = $slugs->isEmpty()
            ? collect()
            : Product::query()
                ->with([
                    'brand:id,name',
                    'category:id,name,slug',
                    'variants' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id'),
                ])
                ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->approved()])
                ->withAvg(['reviews as approved_reviews_average' => fn ($query) => $query->approved()], 'rating')
                ->active()
                ->whereIn('slug', $slugs)
                ->get()
                ->sortBy(fn (Product $product) => $rank[$product->slug] ?? PHP_INT_MAX)
                ->values();

        return Inertia::render('Store/Compare', [
            'products' => $products,
        ]);
    }
}
