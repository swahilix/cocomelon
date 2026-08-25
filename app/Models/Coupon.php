<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Coupon extends Model
{
    protected $fillable = [
        'name', 'code', 'discount_type', 'discount_value', 'applies_to', 'minimum_order_amount',
        'starts_at', 'ends_at', 'is_active', 'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon): void {
            $coupon->code = strtoupper(trim($coupon->code));
        });
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'coupon_brand');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function evaluate(Collection $items, float $subtotal): array
    {
        if (! $this->is_active) {
            return $this->invalid('This coupon is currently inactive.');
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return $this->invalid('This coupon is not active yet.');
        }

        if ($this->ends_at && now()->gt($this->ends_at)) {
            return $this->invalid('This coupon has expired.');
        }

        $eligibleSubtotal = match ($this->applies_to) {
            'category' => $this->eligibleSubtotalForIds($items, 'category_id', $this->categories->pluck('id')),
            'product' => $this->eligibleSubtotalForIds($items, 'id', $this->products->pluck('id')),
            'brand' => $this->eligibleSubtotalForIds($items, 'brand_id', $this->brands->pluck('id')),
            'order_amount' => $this->eligibleOrderAmount($subtotal),
            default => $subtotal,
        };

        if ($eligibleSubtotal <= 0) {
            return $this->invalid(match ($this->applies_to) {
                'category' => 'This coupon does not apply to any category currently in your cart.',
                'product' => 'This coupon does not apply to any product currently in your cart.',
                'brand' => 'This coupon does not apply to any brand currently in your cart.',
                'order_amount' => 'Your order has not reached the minimum amount required for this coupon.',
                default => 'This coupon cannot be applied to the current cart.',
            });
        }

        $discount = $this->discount_type === 'percentage'
            ? $eligibleSubtotal * min(100, (float) $this->discount_value) / 100
            : min($eligibleSubtotal, (float) $this->discount_value);

        return [
            'valid' => true,
            'message' => 'Coupon applied successfully.',
            'discount' => round(max(0, $discount), 2),
            'eligible_subtotal' => round($eligibleSubtotal, 2),
        ];
    }

    protected function eligibleSubtotalForIds(Collection $items, string $field, Collection $ids): float
    {
        if ($ids->isEmpty()) {
            return 0;
        }

        return (float) $items->sum(function (array $line) use ($field, $ids): float {
            $productValue = $field === 'id'
                ? $line['product']->id
                : $line['product']->{$field};

            return $ids->contains($productValue) ? (float) $line['line_total'] : 0;
        });
    }

    protected function eligibleOrderAmount(float $subtotal): float
    {
        $minimum = (float) ($this->minimum_order_amount ?? 0);

        return $subtotal >= $minimum ? $subtotal : 0;
    }

    protected function invalid(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'discount' => 0,
            'eligible_subtotal' => 0,
        ];
    }
}
