<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class CartService
{
    public function raw(): array
    {
        return session('cart', []);
    }

    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): void
    {
        $this->assertVariant($product, $variant);
        abort_unless($product->isLive() && ($variant ? $variant->in_stock : $product->in_stock), 422, 'This product is not available.');

        $cart = $this->normalize($this->raw());
        $key = $this->key($product, $variant);
        $current = (int) ($cart[$key]['quantity'] ?? 0);
        $next = min(99, $current + max(1, $quantity));
        $available = $variant ? (int) $variant->stock_quantity : ($product->track_stock ? (int) $product->stock_quantity : 99);

        $cart[$key] = [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => min($next, max(0, $available)),
        ];

        session(['cart' => $cart]);
    }

    public function update(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        $this->assertVariant($product, $variant);
        $cart = $this->normalize($this->raw());
        $key = $this->key($product, $variant);

        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            $available = $variant ? (int) $variant->stock_quantity : ($product->track_stock ? (int) $product->stock_quantity : 99);
            $cart[$key] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => min(99, max(0, min($quantity, $available))),
            ];
        }

        session(['cart' => array_filter($cart, fn ($line) => (int) ($line['quantity'] ?? 0) > 0)]);
    }

    public function remove(Product $product, ?ProductVariant $variant = null): void
    {
        $this->assertVariant($product, $variant);
        $cart = $this->normalize($this->raw());
        unset($cart[$this->key($product, $variant)]);
        session(['cart' => $cart]);
    }

    public function clear(): void
    {
        session()->forget(['cart', 'cart_coupon_code']);
    }

    public function items(): Collection
    {
        $cart = $this->normalize($this->raw());
        if (! $cart) {
            return collect();
        }

        $productIds = collect($cart)->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();
        $variantIds = collect($cart)->pluck('variant_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
        $variants = $variantIds->isEmpty()
            ? collect()
            : ProductVariant::query()->with('product')->whereIn('id', $variantIds)->get()->keyBy('id');

        return collect($cart)->map(function (array $entry, string $key) use ($products, $variants): ?array {
            $product = $products->get((int) $entry['product_id']);
            if (! $product) return null;

            $variant = ! empty($entry['variant_id']) ? $variants->get((int) $entry['variant_id']) : null;
            if (! empty($entry['variant_id']) && (! $variant || (int) $variant->product_id !== (int) $product->id || ! $variant->is_active)) {
                return null;
            }

            $quantity = (int) $entry['quantity'];
            $unitPrice = $variant ? $variant->current_price : $product->current_price;
            $available = $variant ? (int) $variant->stock_quantity : ($product->track_stock ? (int) $product->stock_quantity : 99);
            $effectiveQuantity = min($quantity, max(0, $available));

            return [
                'cart_key' => $key,
                'product' => $product,
                'variant' => $variant,
                'quantity' => $effectiveQuantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $effectiveQuantity, 2),
                'image_url' => $variant?->image_url ?: $product->image_url,
                'display_name' => $variant ? $product->name.' — '.$variant->display_name : $product->name,
                'max_quantity' => max(0, $available),
            ];
        })->filter(fn ($line) => $line && $line['quantity'] > 0)->values();
    }

    public function applyCoupon(string $code): array
    {
        $code = strtoupper(trim($code));
        $items = $this->items();
        $subtotal = round((float) $items->sum('line_total'), 2);

        if ($items->isEmpty()) {
            return ['valid' => false, 'message' => 'Add products to your cart before applying a coupon.'];
        }

        $coupon = $this->findCoupon($code);
        if (! $coupon) {
            return ['valid' => false, 'message' => 'Coupon code not found.'];
        }

        $evaluation = $coupon->evaluate($items, $subtotal);
        if (! $evaluation['valid']) {
            return $evaluation;
        }

        session(['cart_coupon_code' => $coupon->code]);

        return [
            ...$evaluation,
            'coupon' => $this->couponPayload($coupon, (float) $evaluation['discount']),
        ];
    }

    public function removeCoupon(): void
    {
        session()->forget('cart_coupon_code');
    }

    public function summary(): array
    {
        $items = $this->items();
        $subtotal = round((float) $items->sum('line_total'), 2);
        $shipping = round((float) $items->sum(
            fn (array $line): float => ((float) ($line['product']->shipping_price ?? 0)) * $line['quantity']
        ), 2);

        $discount = 0.0;
        $couponPayload = null;
        $couponCode = session('cart_coupon_code');

        if ($couponCode && $items->isNotEmpty()) {
            $coupon = $this->findCoupon((string) $couponCode);
            $evaluation = $coupon?->evaluate($items, $subtotal);

            if ($coupon && $evaluation && $evaluation['valid']) {
                $discount = round((float) $evaluation['discount'], 2);
                $couponPayload = $this->couponPayload($coupon, $discount);
            } else {
                $this->removeCoupon();
            }
        } elseif ($items->isEmpty()) {
            $this->removeCoupon();
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'coupon' => $couponPayload,
            'total' => round(max(0, $subtotal - $discount) + $shipping, 2),
            'count' => $items->sum('quantity'),
        ];
    }

    protected function normalize(array $cart): array
    {
        $normalized = [];

        foreach ($cart as $key => $entry) {
            if (is_array($entry)) {
                $productId = (int) ($entry['product_id'] ?? 0);
                $variantId = ! empty($entry['variant_id']) ? (int) $entry['variant_id'] : null;
                $quantity = (int) ($entry['quantity'] ?? 0);
            } else {
                $productId = (int) $key;
                $variantId = null;
                $quantity = (int) $entry;
            }

            if ($productId <= 0 || $quantity <= 0) continue;

            $normalized[$this->keyFromIds($productId, $variantId)] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    protected function key(Product $product, ?ProductVariant $variant): string
    {
        return $this->keyFromIds((int) $product->id, $variant?->id);
    }

    protected function keyFromIds(int $productId, ?int $variantId): string
    {
        return 'p'.$productId.':v'.($variantId ?: 0);
    }

    protected function assertVariant(Product $product, ?ProductVariant $variant): void
    {
        if ($variant && ((int) $variant->product_id !== (int) $product->id || ! $variant->is_active)) {
            abort(422, 'The selected product variant is not available.');
        }
    }

    protected function findCoupon(string $code): ?Coupon
    {
        return Coupon::query()
            ->with(['categories:id', 'products:id', 'brands:id'])
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    protected function couponPayload(Coupon $coupon, float $discount): array
    {
        return [
            'id' => $coupon->id,
            'name' => $coupon->name,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'discount' => $discount,
            'applies_to' => $coupon->applies_to,
            'ends_at' => $coupon->ends_at?->toIso8601String(),
        ];
    }
}
