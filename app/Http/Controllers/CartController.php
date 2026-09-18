<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\ConversionTrackingService;
use App\Services\ProductActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(CartService $cart): Response
    {
        $summary = $cart->summary();
        $cartProductIds = collect($summary['items'])->pluck('product.id')->filter()->values();

        return Inertia::render('Store/Cart', [
            'cart' => $summary,
            'recommended' => Product::query()
                ->with(['brand', 'category'])
                ->withCount(['variants as active_variants_count' => fn ($query) => $query->active()])
                ->active()
                ->when($cartProductIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $cartProductIds))
                ->where(fn ($query) => $query->where('track_stock', false)->orWhere('stock_quantity', '>', 0))
                ->orderByDesc('featured')
                ->orderBy('sort_order')
                ->latest()
                ->take(4)
                ->get(),
        ]);
    }

    public function store(Request $request, Product $product, CartService $cart, ProductActivityService $activity, ConversionTrackingService $conversions): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'variant_id' => ['nullable', 'integer'],
            'intent' => ['nullable', 'in:add_to_cart,buy_now'],
        ]);
        $variant = filled($data['variant_id'] ?? null)
            ? ProductVariant::query()->active()->where('product_id', $product->id)->findOrFail((int) $data['variant_id'])
            : null;

        if ($product->variants()->active()->exists() && ! $variant) {
            return back()->withErrors(['variant_id' => 'Choose a product option before adding this item to the cart.']);
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        $cart->add($product, $quantity, $variant);
        $activity->record($request, $product, 'cart_add');

        $unitPrice = $variant ? $variant->current_price : $product->current_price;
        $conversions->record(
            $request,
            'add_to_cart',
            $product,
            [
                'variant_id' => $variant?->id,
                'variant_sku' => $variant?->sku,
            ],
            $unitPrice * $quantity,
            $quantity,
        );

        if (($data['intent'] ?? null) === 'buy_now') {
            $conversions->record(
                $request,
                'buy_now',
                $product,
                [
                    'variant_id' => $variant?->id,
                    'variant_sku' => $variant?->sku,
                ],
                $unitPrice * $quantity,
                $quantity,
            );
        }

        return back()->with('success', 'Product added to cart.');
    }

    public function update(Request $request, Product $product, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
            'variant_id' => ['nullable', 'integer'],
        ]);
        $variant = filled($data['variant_id'] ?? null)
            ? ProductVariant::query()->where('product_id', $product->id)->findOrFail((int) $data['variant_id'])
            : null;
        $cart->update($product, (int) $data['quantity'], $variant);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Request $request, Product $product, CartService $cart): RedirectResponse
    {
        $variantId = $request->integer('variant_id');
        $variant = $variantId
            ? ProductVariant::query()->where('product_id', $product->id)->findOrFail($variantId)
            : null;
        $cart->remove($product, $variant);

        return back()->with('success', 'Product removed.');
    }

    public function clear(CartService $cart): RedirectResponse
    {
        $cart->clear();

        return back()->with('success', 'Cart cleared.');
    }

    public function applyCoupon(Request $request, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $result = $cart->applyCoupon($data['code']);

        if (! $result['valid']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    public function removeCoupon(CartService $cart): RedirectResponse
    {
        $cart->removeCoupon();

        return back()->with('success', 'Coupon removed.');
    }
}
