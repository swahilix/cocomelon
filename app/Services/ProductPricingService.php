<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets selling prices and customer discounts on products and variants, and
 * records every change in product_price_changes.
 *
 * A "discount" here is always a customer discount: it produces the product's
 * sale price (what customers pay), e.g. sell 500,000 with a 50,000 discount.
 */
class ProductPricingService
{
    public const SOURCES = ['opening_stock', 'purchase', 'manual'];

    /**
     * Work out the customer sale price from a selling price and a discount.
     * Returns null when there is no discount.
     */
    public function salePriceFor(float $sellingPrice, string $discountType, ?float $discountValue, string $errorKey = 'discount_value'): ?float
    {
        $value = round(max(0, (float) $discountValue), 2);

        if ($discountType === 'none' || $value <= 0) {
            return null;
        }

        if ($discountType === 'percent') {
            if ($value >= 100) {
                throw ValidationException::withMessages([$errorKey => 'A percentage discount must be less than 100%.']);
            }

            return round($sellingPrice * (1 - $value / 100), 2);
        }

        if ($value >= $sellingPrice) {
            throw ValidationException::withMessages([$errorKey => 'The discount must be smaller than the selling price.']);
        }

        return round($sellingPrice - $value, 2);
    }

    /**
     * Apply a selling price (and optional sale price) to a product or one of its
     * variants. Returns true when anything changed.
     */
    public function apply(
        Product $product,
        ?ProductVariant $variant,
        float $price,
        ?float $salePrice,
        string $source,
        ?string $reference = null,
        ?int $userId = null,
        ?string $saleStartsAt = null,
        ?string $saleEndsAt = null,
        ?string $notes = null,
    ): bool {
        $price = round($price, 2);
        $salePrice = $salePrice !== null ? round($salePrice, 2) : null;

        if ($price <= 0) {
            throw ValidationException::withMessages(['selling_price' => 'Selling price must be greater than zero.']);
        }

        if ($salePrice !== null && ($salePrice <= 0 || $salePrice >= $price)) {
            throw ValidationException::withMessages(['discount_value' => 'The discounted price must be above zero and below the selling price.']);
        }

        return DB::transaction(function () use ($product, $variant, $price, $salePrice, $source, $reference, $userId, $saleStartsAt, $saleEndsAt, $notes): bool {
            $target = $variant
                ? ProductVariant::query()->lockForUpdate()->findOrFail($variant->id)
                : Product::query()->lockForUpdate()->findOrFail($product->id);

            $oldPrice = $target->price !== null ? (float) $target->price : null;
            $oldSale = $target->sale_price !== null ? (float) $target->sale_price : null;
            $oldStarts = $variant ? null : $target->sale_starts_at?->toDateTimeString();
            $oldEnds = $variant ? null : $target->sale_ends_at?->toDateTimeString();
            $newStarts = $variant || $salePrice === null ? null : $this->normalizeDate($saleStartsAt);
            $newEnds = $variant || $salePrice === null ? null : $this->normalizeDate($saleEndsAt);

            $unchanged = $oldPrice !== null
                && abs($oldPrice - $price) < 0.005
                && (($oldSale === null && $salePrice === null) || ($oldSale !== null && $salePrice !== null && abs($oldSale - $salePrice) < 0.005))
                && $oldStarts === $newStarts
                && $oldEnds === $newEnds;

            if ($unchanged) {
                return false;
            }

            $target->price = $price;
            $target->sale_price = $salePrice;
            if (! $variant) {
                $target->sale_starts_at = $newStarts;
                $target->sale_ends_at = $newEnds;
            }
            $target->save();

            DB::table('product_price_changes')->insert([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'old_price' => $oldPrice,
                'new_price' => $price,
                'old_sale_price' => $oldSale,
                'new_sale_price' => $salePrice,
                'sale_starts_at' => $newStarts,
                'sale_ends_at' => $newEnds,
                'source' => $source,
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($variant) {
                $this->syncParentFromVariants($product);
            }

            return true;
        });
    }

    /**
     * A product with variants shows its cheapest active variant price, so the
     * storefront "from" price and listings stay correct.
     */
    public function syncParentFromVariants(Product $product): void
    {
        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereNotNull('price')
            ->get(['price', 'sale_price']);

        if ($variants->isEmpty()) {
            return;
        }

        $cheapest = $variants->sortBy(fn (ProductVariant $variant) => $this->effective($variant->price, $variant->sale_price))->first();
        $price = round((float) $cheapest->price, 2);
        $sale = $cheapest->sale_price !== null && (float) $cheapest->sale_price < $price ? round((float) $cheapest->sale_price, 2) : null;

        $parent = Product::query()->lockForUpdate()->findOrFail($product->id);
        $parent->forceFill([
            'price' => $price,
            'sale_price' => $sale,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
        ]);

        if ($parent->isDirty()) {
            $parent->save();
        }
    }

    private function effective(mixed $price, mixed $sale): float
    {
        $price = (float) $price;

        return $sale !== null && (float) $sale < $price ? (float) $sale : $price;
    }

    private function normalizeDate(?string $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->toDateTimeString() : null;
    }
}
