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

        $availableProducts = Product::query()
            ->with(['brand:id,name', 'category:id,name,slug'])
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->approved()])
            ->withAvg(['reviews as approved_reviews_average' => fn ($query) => $query->approved()], 'rating')
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image_url' => $product->image_url,
                'current_price' => $product->current_price,
                'price' => (float) $product->price,
                'in_stock' => $product->in_stock,
                'stock_message' => $product->stock_message,
                'stock_quantity' => (int) $product->stock_quantity,
                'low_stock' => (bool) $product->low_stock,
                'low_stock_threshold' => (int) $product->low_stock_threshold,
                'warranty' => $product->warranty,
                'brand' => $product->brand ? ['id' => $product->brand->id, 'name' => $product->brand->name] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'approved_reviews_count' => (int) $product->approved_reviews_count,
                'approved_reviews_average' => (float) ($product->approved_reviews_average ?? 0),
            ])
            ->values();

        return Inertia::render('Store/Compare', [
            'products' => $products,
            'availableProducts' => $availableProducts,
        ]);
    }
}
