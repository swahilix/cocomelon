<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Posts opening stock for one or more products/variants: quantity and cost go
 * into stock immediately, and the selling price and customer discount are set.
 */
class OpeningStockService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ProductPricingService $pricing,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  product_id, product_variant_id, quantity, unit_cost, selling_price, discount_type, discount_value
     * @return int number of lines posted
     */
    public function post(array $lines, ?string $notes, bool $publish, ?int $userId): int
    {
        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('is_active', true)])
            ->whereIn('id', collect($lines)->pluck('product_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $prepared = collect($lines)->values()->map(function (array $line, int $index) use ($products): array {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            if (! $product) {
                throw ValidationException::withMessages(["lines.$index.product_id" => 'Choose a product.']);
            }

            $variant = $this->variant($product, $line['product_variant_id'] ?? null, "lines.$index.product_variant_id");
            $selling = round((float) $line['selling_price'], 2);

            return [
                'product' => $product,
                'variant' => $variant,
                'quantity' => (int) $line['quantity'],
                'unit_cost' => round((float) $line['unit_cost'], 2),
                'selling_price' => $selling,
                'sale_price' => $this->pricing->salePriceFor(
                    $selling,
                    (string) ($line['discount_type'] ?? 'none'),
                    isset($line['discount_value']) ? (float) $line['discount_value'] : null,
                    "lines.$index.discount_value",
                ),
            ];
        });

        $duplicate = $prepared->countBy(fn ($line) => $line['product']->id.'-'.($line['variant']?->id ?? 0))->filter(fn ($count) => $count > 1);
        if ($duplicate->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Each product (or variant) can appear only once in opening stock.']);
        }

        DB::transaction(function () use ($prepared, $notes, $publish, $userId): void {
            foreach ($prepared as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $reference = 'OPEN-'.$product->id.'-'.now()->format('ymd-His').'-'.strtoupper(Str::random(3));

                $this->inventory->openingStock($product, $line['variant'], $line['quantity'], $line['unit_cost'], $notes, $userId, $reference);
                $this->pricing->apply($product, $line['variant'], $line['selling_price'], $line['sale_price'], 'opening_stock', $reference, $userId);
            }

            if ($publish) {
                Product::query()
                    ->whereIn('id', $prepared->map(fn ($line) => $line['product']->id)->unique()->all())
                    ->where('status', 'draft')
                    ->where('price', '>', 0)
                    ->get()
                    ->each(fn (Product $product) => $product->update(['status' => 'active']));
            }
        });

        return $prepared->count();
    }

    private function variant(Product $product, mixed $variantId, string $errorKey): ?ProductVariant
    {
        $variantId = (int) ($variantId ?? 0);

        if ($product->variants->isEmpty()) {
            if ($variantId > 0) {
                throw ValidationException::withMessages([$errorKey => "{$product->name} has no variants."]);
            }

            return null;
        }

        $variant = $product->variants->firstWhere('id', $variantId);
        if (! $variant) {
            throw ValidationException::withMessages([$errorKey => "Choose which variant of {$product->name}."]);
        }

        return $variant;
    }
}
