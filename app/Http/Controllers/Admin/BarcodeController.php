<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class BarcodeController extends Controller
{
    public function __invoke(): Response
    {
        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('name')
            ->get(['id', 'category_id', 'brand_id', 'name', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock_quantity', 'track_stock', 'status'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $product->current_price,
                'stock_quantity' => (int) $product->stock_quantity,
                'track_stock' => (bool) $product->track_stock,
                'status' => $product->status,
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
            ])
            ->values();

        return Inertia::render('Admin/Barcodes/Index', [
            'products' => $products,
        ]);
    }
}
