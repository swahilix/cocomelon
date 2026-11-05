<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\OpeningStockService;
use App\Services\ProductIntelligence\MarketPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * "Add stock": opening stock or a purchase for any number of products,
 * separate from entering product details.
 */
class StockEntryController extends Controller
{
    public function show(Request $request, MarketPriceService $prices): Response
    {
        $user = $request->user();

        $movedProducts = DB::table('stock_movements')
            ->where('affects_stock', true)
            ->whereNull('product_variant_id')
            ->distinct()
            ->pluck('product_id')
            ->flip();
        $movedVariants = DB::table('stock_movements')
            ->whereNotNull('product_variant_id')
            ->distinct()
            ->pluck('product_variant_id')
            ->flip();

        $products = Product::query()
            ->with(['brand:id,name', 'variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->whereIn('status', ['draft', 'active'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'slug', 'brand_id', 'status', 'price', 'sale_price', 'stock_quantity', 'track_stock'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'brand' => $product->brand?->name,
                'status' => $product->status,
                'stock_quantity' => (int) $product->stock_quantity,
                'selling_price' => (float) $product->price > 0 ? (float) $product->price : null,
                'opening_allowed' => $product->variants->isEmpty() && (int) $product->stock_quantity === 0 && ! $movedProducts->has($product->id),
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->display_name,
                    'stock_quantity' => (int) $variant->stock_quantity,
                    'selling_price' => $variant->price !== null ? (float) $variant->price : null,
                    'opening_allowed' => (int) $variant->stock_quantity === 0 && ! $movedVariants->has($variant->id),
                ])->values(),
            ]);

        $mode = in_array($request->query('mode'), ['opening', 'purchase'], true) ? $request->query('mode') : null;

        // Converting a purchase order: start a purchase with its items.
        $purchaseOrder = null;
        if ($request->integer('purchase_order') > 0) {
            $order = DB::table('purchase_orders')->where('id', $request->integer('purchase_order'))->first();
            if ($order && in_array($order->status, PurchaseOrderController::OPEN_STATUSES, true)) {
                $purchaseOrder = [
                    'id' => $order->id,
                    'reference' => $order->reference,
                    'supplier_id' => $order->supplier_id,
                    'supplier_name' => $order->supplier_name,
                    'lines' => DB::table('purchase_order_items')
                        ->where('purchase_order_id', $order->id)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($item) => [
                            'product_id' => (int) $item->product_id,
                            'product_variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : '',
                            'quantity' => (int) $item->quantity,
                            'unit_cost' => $item->estimated_unit_cost !== null ? (float) $item->estimated_unit_cost : '',
                        ])
                        ->values(),
                ];
                $mode = 'purchase';
            }
        }

        return Inertia::render('Admin/Stock/Entry', [
            'products' => $products,
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'initialProductId' => (int) $request->query('product', 0) ?: null,
            'initialMode' => $mode,
            'purchaseOrder' => $purchaseOrder,
            'initialSupplierId' => (int) $request->query('supplier', 0) ?: null,
            'marketPriceEnabled' => $prices->enabled(),
            'can' => [
                'opening' => (bool) $user?->hasAnyPermission(['adjust_stock']),
                'purchase' => (bool) $user?->hasAnyPermission(['manage_purchases']),
                'receive' => (bool) $user?->hasAnyPermission(['receive_purchases']),
            ],
        ]);
    }

    public function opening(Request $request, OpeningStockService $openingStock): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.product_variant_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.selling_price' => ['required', 'numeric', 'gt:0'],
            'lines.*.discount_type' => ['nullable', 'in:none,amount,percent'],
            'lines.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'publish' => ['boolean'],
        ]);

        try {
            $count = $openingStock->post($validated['lines'], $validated['notes'] ?? null, (bool) ($validated['publish'] ?? false), $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()]);
        }

        return back()->with('success', $count === 1
            ? 'Opening stock added. Stock, cost and selling price are updated.'
            : "Opening stock added for {$count} items. Stock, cost and selling prices are updated.");
    }
}
