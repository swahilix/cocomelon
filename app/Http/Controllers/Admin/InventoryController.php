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
        $reversedIds = DB::table('stock_movements')
            ->whereNotNull('reversal_of_id')
            ->pluck('reversal_of_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $movementTotals = DB::table('stock_movements')
            ->selectRaw('product_id, COUNT(*) as movement_count, COALESCE(SUM(quantity), 0) as movement_delta')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->select([
                'id', 'category_id', 'brand_id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold',
                'track_stock', 'price', 'sale_price', 'primary_image',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($movementTotals): array {
                $lastMovement = DB::table('stock_movements')->where('product_id', $product->id)->latest('id')->first();
                $firstMovement = DB::table('stock_movements')->where('product_id', $product->id)->oldest('id')->first();
                $movementTotal = $movementTotals->get($product->id);
                $lastCost = (float) (DB::table('stock_purchase_items')->where('product_id', $product->id)->latest('id')->value('unit_cost') ?? 0);
                $status = ! $product->track_stock
                    ? 'not_tracked'
                    : ($product->stock_quantity <= 0
                        ? 'out_of_stock'
                        : ($product->stock_quantity <= $product->low_stock_threshold ? 'low_stock' : 'in_stock'));

                $expectedBalance = null;
                $reconciliation = 'not_tracked';

                if ($product->track_stock) {
                    if (! $firstMovement) {
                        $reconciliation = 'opening_required';
                    } elseif ($firstMovement->balance_before === null) {
                        $reconciliation = 'unverifiable';
                    } else {
                        $expectedBalance = (int) $firstMovement->balance_before + (int) ($movementTotal->movement_delta ?? 0);
                        $reconciliation = $expectedBalance === (int) $product->stock_quantity ? 'matched' : 'mismatch';
                    }
                }

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
                    'movement_count' => (int) ($movementTotal->movement_count ?? 0),
                    'expected_balance' => $expectedBalance,
                    'reconciliation' => $reconciliation,
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

        $recentMovements = DB::table('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.created_by')
            ->select(
                'stock_movements.*',
                'products.name as product_name',
                'products.sku',
                'users.name as performed_by',
            )
            ->latest('stock_movements.id')
            ->limit(30)
            ->get()
            ->map(function ($movement) use ($reversedIds): array {
                $manualTypes = ['opening_balance', 'adjustment_increase', 'adjustment_decrease'];
                $isReversed = in_array((int) $movement->id, $reversedIds, true);

                return [
                    'id' => $movement->id,
                    'product_name' => $movement->product_name,
                    'sku' => $movement->sku,
                    'type' => $movement->type,
                    'reason' => $movement->reason,
                    'quantity' => $movement->quantity,
                    'balance_before' => $movement->balance_before,
                    'balance_after' => $movement->balance_after,
                    'reference' => $movement->reference,
                    'notes' => $movement->notes,
                    'performed_by' => $movement->performed_by,
                    'created_at' => $movement->created_at,
                    'is_reversed' => $isReversed,
                    'can_reverse' => in_array($movement->type, $manualTypes, true)
                        && ! $isReversed
                        && $movement->reversal_of_id === null
                        && (int) $movement->quantity !== 0,
                ];
            });

        return Inertia::render('Admin/Stock/Inventory', [
            'products' => $products,
            'categories' => $products->pluck('category')->filter()->unique()->sort()->values(),
            'brands' => $products->pluck('brand')->filter()->unique()->sort()->values(),
            'recentMovements' => $recentMovements,
            'summary' => [
                'tracked_products' => $products->where('track_stock', true)->count(),
                'units' => $products->where('track_stock', true)->sum('stock_quantity'),
                'low_stock' => $products->where('status', 'low_stock')->count(),
                'out_of_stock' => $products->where('status', 'out_of_stock')->count(),
                'value' => $products->sum('stock_value'),
                'reconciliation_mismatches' => $products->where('reconciliation', 'mismatch')->count(),
                'opening_required' => $products->where('reconciliation', 'opening_required')->count(),
            ],
        ]);
    }
}
