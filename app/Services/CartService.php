<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Collection;

class CartService
{
    public function raw(): array
    {
        return session('cart', []);
    }

    public function add(Product $product, int $quantity = 1): void
    {
        abort_unless($product->status === 'active' && $product->in_stock, 422, 'This product is not available.');

        $cart = $this->raw();
        $next = min(99, ($cart[$product->id] ?? 0) + max(1, $quantity));

        if ($product->track_stock) {
            $next = min($next, $product->stock_quantity);
        }

        $cart[$product->id] = $next;
        session(['cart' => $cart]);
    }

    public function update(Product $product, int $quantity): void
    {
        $cart = $this->raw();

        if ($quantity <= 0) {
            unset($cart[$product->id]);
        } else {
            $quantity = min(99, $quantity);
            if ($product->track_stock) {
                $quantity = min($quantity, $product->stock_quantity);
            }
            $cart[$product->id] = $quantity;
        }

        session(['cart' => $cart]);
    }

    public function remove(Product $product): void
    {
        $cart = $this->raw();
        unset($cart[$product->id]);
        session(['cart' => $cart]);
    }

    public function clear(): void
    {
        session()->forget(['cart', 'cart_coupon_code']);
    }

    public function items(): Collection
    {
        $cart = $this->raw();
        if (! $cart) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', array_keys($cart))
            ->get()
            ->map(function (Product $product) use ($cart): array {
                $quantity = (int) $cart[$product->id];

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $product->current_price,
                    'line_total' => round($product->current_price * $quantity, 2),
                ];
            })
            ->values();
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
