<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __invoke(): Response
    {
        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->select([
                'id', 'category_id', 'brand_id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold',
                'track_stock', 'price', 'sale_price', 'primary_image',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product): array {
                $lastMovement = DB::table('stock_movements')->where('product_id', $product->id)->latest('id')->first();
                $lastCost = (float) (DB::table('stock_purchase_items')->where('product_id', $product->id)->latest('id')->value('unit_cost') ?? 0);
                $status = ! $product->track_stock
                    ? 'not_tracked'
                    : ($product->stock_quantity <= 0
                        ? 'out_of_stock'
                        : ($product->stock_quantity <= $product->low_stock_threshold ? 'low_stock' : 'in_stock'));

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'brand' => $product->brand?->name,
                    'stock_quantity' => $product->stock_quantity,
                    'low_stock_threshold' => $product->low_stock_threshold,
                    'track_stock' => $product->track_stock,
                    'price' => $product->current_price,
                    'image_url' => $product->image_url,
                    'status' => $status,
                    'last_cost' => $lastCost,
                    'stock_value' => $product->track_stock ? $lastCost * $product->stock_quantity : 0,
                    'has_movements' => (bool) $lastMovement,
                    'last_movement' => $lastMovement ? [
                        'id' => $lastMovement->id,
                        'type' => $lastMovement->type,
                        'reason' => $lastMovement->reason,
                        'quantity' => $lastMovement->quantity,
                        'balance_before' => $lastMovement->balance_before,
                        'balance_after' => $lastMovement->balance_after,
                        'created_at' => $lastMovement->created_at,
                    ] : null,
                ];
            });

        return Inertia::render('Admin/Stock/Inventory', [
            'products' => $products,
            'categories' => $products->pluck('category')->filter()->unique()->sort()->values(),
            'brands' => $products->pluck('brand')->filter()->unique()->sort()->values(),
            'summary' => [
                'tracked_products' => $products->where('track_stock', true)->count(),
                'units' => $products->where('track_stock', true)->sum('stock_quantity'),
                'low_stock' => $products->where('status', 'low_stock')->count(),
                'out_of_stock' => $products->where('status', 'out_of_stock')->count(),
                'value' => $products->sum('stock_value'),
            ],
        ]);
    }
}
