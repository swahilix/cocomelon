<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\ProductIntelligence\MarketPriceService;
use App\Services\ProductPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 2 of product entry: opening stock, purchases and selling prices for one
 * product (and its variants).
 */
class ProductStockController extends Controller
{
    public function show(Request $request, Product $product, MarketPriceService $prices): Response
    {
        $product->load(['variants' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);
        $user = $request->user();

        $variantsWithHistory = DB::table('stock_movements')
            ->where('product_id', $product->id)
            ->whereNotNull('product_variant_id')
            ->distinct()
            ->pluck('product_variant_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $productHasHistory = (int) $product->stock_quantity > 0
            || DB::table('stock_movements')->where('product_id', $product->id)->where('affects_stock', true)->exists();

        $variantNames = $product->variants->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => $variant->display_name]);

        return Inertia::render('Admin/Products/Stock', [
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'sku' => $product->sku,
                'image_url' => $product->image_url,
                'status' => $product->status,
                'track_stock' => (bool) $product->track_stock,
                'stock_quantity' => (int) $product->stock_quantity,
                'low_stock_threshold' => (int) $product->low_stock_threshold,
                'warranty' => $product->warranty,
                'price' => (float) $product->price,
                'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
                'sale_starts_at' => $product->sale_starts_at?->format('Y-m-d\TH:i'),
                'sale_ends_at' => $product->sale_ends_at?->format('Y-m-d\TH:i'),
                'current_price' => $product->current_price,
                'average_cost' => (float) $product->average_cost,
                'opening_allowed' => ! $productHasHistory,
            ],
            'variants' => $product->variants->where('is_active', true)->values()->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'name' => $variant->display_name,
                'sku' => $variant->sku,
                'stock_quantity' => (int) $variant->stock_quantity,
                'price' => $variant->price !== null ? (float) $variant->price : null,
                'sale_price' => $variant->sale_price !== null ? (float) $variant->sale_price : null,
                'opening_allowed' => (int) $variant->stock_quantity === 0 && ! in_array($variant->id, $variantsWithHistory, true),
            ]),
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'priceHistory' => DB::table('product_price_changes')
                ->leftJoin('users', 'users.id', '=', 'product_price_changes.created_by')
                ->where('product_price_changes.product_id', $product->id)
                ->select('product_price_changes.*', 'users.name as changed_by')
                ->latest('product_price_changes.id')
                ->limit(30)
                ->get()
                ->map(fn ($row) => [...(array) $row, 'variant_name' => $row->product_variant_id ? ($variantNames[$row->product_variant_id] ?? 'Removed variant') : null]),
            'movements' => DB::table('stock_movements')
                ->leftJoin('users', 'users.id', '=', 'stock_movements.created_by')
                ->where('stock_movements.product_id', $product->id)
                ->select('stock_movements.id', 'stock_movements.type', 'stock_movements.quantity', 'stock_movements.balance_after', 'stock_movements.unit_cost', 'stock_movements.reference', 'stock_movements.product_variant_id', 'stock_movements.created_at', 'users.name as performed_by')
                ->latest('stock_movements.id')
                ->limit(30)
                ->get()
                ->map(fn ($row) => [...(array) $row, 'variant_name' => $row->product_variant_id ? ($variantNames[$row->product_variant_id] ?? 'Removed variant') : null]),
            'purchases' => DB::table('stock_purchase_items')
                ->join('stock_purchases', 'stock_purchases.id', '=', 'stock_purchase_items.stock_purchase_id')
                ->where('stock_purchase_items.product_id', $product->id)
                ->select('stock_purchases.id', 'stock_purchases.reference', 'stock_purchases.status', 'stock_purchases.purchase_date', 'stock_purchases.supplier_name', 'stock_purchase_items.quantity', 'stock_purchase_items.quantity_received', 'stock_purchase_items.unit_cost', 'stock_purchase_items.product_variant_id')
                ->latest('stock_purchases.id')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [...(array) $row, 'variant_name' => $row->product_variant_id ? ($variantNames[$row->product_variant_id] ?? 'Removed variant') : null]),
            'marketPriceEnabled' => $prices->enabled(),
            'can' => [
                'opening' => (bool) $user?->hasAnyPermission(['adjust_stock']),
                'purchase' => (bool) $user?->hasAnyPermission(['manage_purchases']),
                'receive' => (bool) $user?->hasAnyPermission(['receive_purchases']),
                'price' => (bool) $user?->hasAnyPermission(['manage_storefront']),
                'view_costs' => (bool) $user?->hasAnyPermission(['view_costs', 'manage_purchases']),
            ],
        ]);
    }

    public function updatePrice(Request $request, Product $product, ProductPricingService $pricing): RedirectResponse
    {
        $validated = $request->validate([
            'product_variant_id' => ['nullable', 'integer'],
            'selling_price' => ['required', 'numeric', 'gt:0'],
            'discount_type' => ['required', 'in:none,amount,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after:sale_starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $variants = $product->variants()->where('is_active', true)->get()->keyBy('id');
        $variant = $this->resolveVariant($product, $variants, $validated['product_variant_id'] ?? null, 'product_variant_id');
        $selling = round((float) $validated['selling_price'], 2);
        $sale = $pricing->salePriceFor($selling, $validated['discount_type'], isset($validated['discount_value']) ? (float) $validated['discount_value'] : null);

        $changed = $pricing->apply(
            $product,
            $variant,
            $selling,
            $sale,
            'manual',
            null,
            $request->user()?->id,
            $variant ? null : ($validated['sale_starts_at'] ?? null),
            $variant ? null : ($validated['sale_ends_at'] ?? null),
            $validated['notes'] ?? null,
        );

        return back()->with('success', $changed ? 'Price updated.' : 'The price was already set to these values.');
    }

    private function resolveVariant(Product $product, $variants, mixed $variantId, string $errorKey): ?ProductVariant
    {
        $variantId = (int) ($variantId ?? 0);

        if ($variants->isEmpty()) {
            if ($variantId > 0) {
                throw ValidationException::withMessages([$errorKey => "{$product->name} has no variants."]);
            }

            return null;
        }

        $variant = $variants->get($variantId);
        if (! $variant) {
            throw ValidationException::withMessages([$errorKey => 'Choose a variant.']);
        }

        return $variant;
    }
}
