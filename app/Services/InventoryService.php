<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class InventoryService
{
    public function increase(
        Product|int $product,
        int $quantity,
        string $type,
        ?string $reference = null,
        ?float $unitCost = null,
        ?string $notes = null,
        ?int $userId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $reason = null,
    ): object {
        return $this->mutate($product, abs($quantity), $type, $reference, $unitCost, $notes, $userId, $sourceType, $sourceId, $reason);
    }

    public function decrease(
        Product|int $product,
        int $quantity,
        string $type,
        ?string $reference = null,
        ?float $unitCost = null,
        ?string $notes = null,
        ?int $userId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $reason = null,
    ): object {
        return $this->mutate($product, -abs($quantity), $type, $reference, $unitCost, $notes, $userId, $sourceType, $sourceId, $reason);
    }

    public function openingBalance(Product|int $product, int $quantity, ?string $notes = null, ?int $userId = null): object
    {
        $productId = $product instanceof Product ? $product->id : $product;
        $exists = DB::table('stock_movements')
            ->where('product_id', $productId)
            ->where('affects_stock', true)
            ->exists();

        if ($exists) {
            throw new RuntimeException('Opening balance is allowed only for a product without live stock movement history.');
        }

        return $this->setBalance($product, $quantity, 'opening_balance', 'Opening balance', $notes, $userId);
    }

    public function adjust(Product|int $product, int $targetQuantity, string $reason, ?string $notes = null, ?int $userId = null): ?object
    {
        $productModel = $this->lockedProduct($product);
        $current = (int) $productModel->stock_quantity;
        $delta = $targetQuantity - $current;

        if ($delta === 0) {
            return null;
        }

        return $this->mutateLocked(
            $productModel,
            $delta,
            $delta > 0 ? 'adjustment_increase' : 'adjustment_decrease',
            'ADJ-'.now()->format('ymd-His').'-'.$productModel->id,
            null,
            $notes,
            $userId,
            'manual_adjustment',
            null,
            $reason,
        );
    }

    /**
     * First stock for a product (or one of its variants), with its unit cost.
     * Allowed only while that product/variant has no stock and no stock history.
     */
    public function openingStock(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        float $unitCost,
        ?string $notes = null,
        ?int $userId = null,
        ?string $reference = null,
    ): object {
        if ($quantity < 1) {
            throw new RuntimeException('Opening stock quantity must be at least 1.');
        }

        if ($variant) {
            $hasHistory = (int) $variant->stock_quantity > 0
                || ($this->movementsHaveVariantColumn() && DB::table('stock_movements')->where('product_variant_id', $variant->id)->exists());

            if ($hasHistory) {
                throw new RuntimeException("{$variant->display_name} already has stock. Use a purchase or a stock adjustment instead of opening stock.");
            }
        } else {
            $hasHistory = (int) $product->stock_quantity > 0
                || DB::table('stock_movements')->where('product_id', $product->id)->where('affects_stock', true)->exists();

            if ($hasHistory) {
                throw new RuntimeException("{$product->name} already has stock. Use a purchase or a stock adjustment instead of opening stock.");
            }
        }

        return $this->changeStock(
            $product,
            $variant,
            $quantity,
            'opening_stock',
            $reference ?: 'OPEN-'.$product->id.'-'.now()->format('ymd-His'),
            $unitCost,
            $notes,
            $userId,
            'opening_stock',
            null,
            'Opening stock',
        );
    }

    /**
     * Add or remove stock for a product, keeping a variant's own quantity in step
     * with the parent product total when a variant is given.
     */
    public function changeStock(
        Product|int $product,
        ?ProductVariant $variant,
        int $delta,
        string $type,
        ?string $reference = null,
        ?float $unitCost = null,
        ?string $notes = null,
        ?int $userId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $reason = null,
    ): object {
        if ($delta === 0) {
            throw new RuntimeException('Stock movement quantity cannot be zero.');
        }

        return DB::transaction(function () use ($product, $variant, $delta, $type, $reference, $unitCost, $notes, $userId, $sourceType, $sourceId, $reason): object {
            $productModel = $this->lockedProduct($product);

            if ($variant) {
                $lockedVariant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);

                if ((int) $lockedVariant->product_id !== (int) $productModel->id) {
                    throw new RuntimeException('That variant does not belong to this product.');
                }

                $variantAfter = (int) $lockedVariant->stock_quantity + $delta;
                if ($variantAfter < 0) {
                    throw new RuntimeException("Not enough stock for {$lockedVariant->display_name}. Available: {$lockedVariant->stock_quantity}.");
                }

                $lockedVariant->stock_quantity = $variantAfter;
                $lockedVariant->save();

                // Variant stock is always counted in the parent product total.
                if (! $productModel->track_stock) {
                    $productModel->track_stock = true;
                }
            }

            return $this->mutateLocked(
                $productModel,
                $delta,
                $type,
                $reference,
                $unitCost,
                $notes,
                $userId,
                $sourceType,
                $sourceId,
                $reason,
                null,
                $variant?->id,
            );
        });
    }

    public function reverse(int $movementId, ?string $notes = null, ?int $userId = null): object
    {
        return DB::transaction(function () use ($movementId, $notes, $userId): object {
            $movement = DB::table('stock_movements')->lockForUpdate()->find($movementId);

            if (! $movement) {
                throw new RuntimeException('Stock movement was not found.');
            }

            if (! (bool) ($movement->affects_stock ?? true) || (bool) ($movement->is_historical ?? false)) {
                throw new RuntimeException('Historical reference-only movements cannot be reversed because they never changed current stock. Post a stock adjustment instead.');
            }

            if ($movement->type === 'reversal' || $movement->reversal_of_id !== null) {
                throw new RuntimeException('A reversal movement cannot be reversed again. Post a controlled stock adjustment instead.');
            }

            if ((int) $movement->quantity === 0) {
                throw new RuntimeException('A zero-quantity movement cannot be reversed.');
            }

            if (DB::table('stock_movements')->where('reversal_of_id', $movementId)->exists()) {
                throw new RuntimeException('This stock movement has already been reversed.');
            }

            $product = Product::query()->lockForUpdate()->findOrFail($movement->product_id);

            return $this->mutateLocked(
                $product,
                -1 * (int) $movement->quantity,
                'reversal',
                $movement->reference ? 'REV-'.$movement->reference : 'REV-'.$movement->id,
                $movement->unit_cost !== null ? (float) $movement->unit_cost : null,
                $notes ?: 'Reversal of movement #'.$movement->id,
                $userId,
                'stock_movement',
                $movement->id,
                'Reversal',
                $movement->id,
            );
        });
    }

    private function setBalance(Product|int $product, int $targetQuantity, string $type, string $reason, ?string $notes, ?int $userId): object
    {
        if ($targetQuantity < 0) {
            throw new RuntimeException('Stock quantity cannot be negative.');
        }

        return DB::transaction(function () use ($product, $targetQuantity, $type, $reason, $notes, $userId): object {
            $productModel = $this->lockedProduct($product);
            $delta = $targetQuantity - (int) $productModel->stock_quantity;

            return $this->mutateLocked(
                $productModel,
                $delta,
                $type,
                'OPEN-'.$productModel->id.'-'.now()->format('ymd-His'),
                null,
                $notes,
                $userId,
                'manual_adjustment',
                null,
                $reason,
            );
        });
    }

    private function mutate(
        Product|int $product,
        int $delta,
        string $type,
        ?string $reference,
        ?float $unitCost,
        ?string $notes,
        ?int $userId,
        ?string $sourceType,
        ?int $sourceId,
        ?string $reason,
    ): object {
        if ($delta === 0) {
            throw new RuntimeException('Stock movement quantity cannot be zero.');
        }

        return DB::transaction(function () use ($product, $delta, $type, $reference, $unitCost, $notes, $userId, $sourceType, $sourceId, $reason): object {
            $productModel = $this->lockedProduct($product);

            return $this->mutateLocked($productModel, $delta, $type, $reference, $unitCost, $notes, $userId, $sourceType, $sourceId, $reason);
        });
    }

    private function mutateLocked(
        Product $product,
        int $delta,
        string $type,
        ?string $reference,
        ?float $unitCost,
        ?string $notes,
        ?int $userId,
        ?string $sourceType,
        ?int $sourceId,
        ?string $reason,
        ?int $reversalOfId = null,
        ?int $variantId = null,
    ): object {
        $before = (int) $product->stock_quantity;
        $after = $product->track_stock ? $before + $delta : $before;

        if ($product->track_stock && $after < 0) {
            throw new RuntimeException("Not enough stock for {$product->name}. Available: {$before}.");
        }

        if ($product->track_stock && $unitCost !== null) {
            $oldAverage = (float) $product->average_cost;
            $movementCost = max(0, $unitCost);
            $costedInboundTypes = ['purchase_receipt', 'purchase', 'opening_stock', 'pos_return', 'pos_void', 'online_order_cancellation'];

            if ($delta > 0 && in_array($type, $costedInboundTypes, true)) {
                $newAverage = ($before <= 0 || $oldAverage <= 0)
                    ? $movementCost
                    : (($before * $oldAverage) + ($delta * $movementCost)) / ($before + $delta);
                $product->average_cost = round($newAverage, 4);

                if (in_array($type, ['purchase_receipt', 'purchase'], true)) {
                    $product->last_purchase_cost = round($movementCost, 4);
                }
            }

            if ($delta < 0 && $type === 'purchase_return') {
                if ($after <= 0) {
                    $product->average_cost = 0;
                } else {
                    $remainingValue = max(0, ($before * $oldAverage) - (abs($delta) * $movementCost));
                    $product->average_cost = round($remainingValue / $after, 4);
                }
            }
        }

        if ($product->track_stock && $delta !== 0) {
            $product->stock_quantity = $after;
        }

        if ($product->isDirty()) {
            $product->save();
        }

        $movement = [
            'product_id' => $product->id,
            'direction' => $delta === 0 ? 'adjustment' : ($delta > 0 ? 'in' : 'out'),
            'type' => $type,
            'quantity' => $delta,
            'balance_before' => $product->track_stock ? $before : null,
            'balance_after' => $product->track_stock ? $after : null,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'unit_cost' => $unitCost,
            'reference' => $reference,
            'reason' => $reason,
            'reversal_of_id' => $reversalOfId,
            'notes' => $notes,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($variantId !== null && $this->movementsHaveVariantColumn()) {
            $movement['product_variant_id'] = $variantId;
        }

        $id = DB::table('stock_movements')->insertGetId($movement);

        return DB::table('stock_movements')->find($id);
    }

    private function movementsHaveVariantColumn(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('stock_movements', 'product_variant_id');
    }

    private function lockedProduct(Product|int $product): Product
    {
        $id = $product instanceof Product ? $product->id : $product;

        return Product::query()->lockForUpdate()->findOrFail($id);
    }
}
