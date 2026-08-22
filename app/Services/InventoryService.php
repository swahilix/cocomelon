<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
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
        $exists = DB::table('stock_movements')->where('product_id', $productId)->exists();

        if ($exists) {
            throw new RuntimeException('Opening balance is allowed only for a product without stock movement history.');
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

    public function reverse(int $movementId, ?string $notes = null, ?int $userId = null): object
    {
        return DB::transaction(function () use ($movementId, $notes, $userId): object {
            $movement = DB::table('stock_movements')->lockForUpdate()->find($movementId);

            if (! $movement) {
                throw new RuntimeException('Stock movement was not found.');
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
    ): object {
        $before = (int) $product->stock_quantity;
        $after = $product->track_stock ? $before + $delta : $before;

        if ($product->track_stock && $after < 0) {
            throw new RuntimeException("Not enough stock for {$product->name}. Available: {$before}.");
        }

        if ($product->track_stock) {
            $product->stock_quantity = $after;
            $product->save();
        }

        $id = DB::table('stock_movements')->insertGetId([
            'product_id' => $product->id,
            'direction' => $delta > 0 ? 'in' : 'out',
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
        ]);

        return DB::table('stock_movements')->find($id);
    }

    private function lockedProduct(Product|int $product): Product
    {
        $id = $product instanceof Product ? $product->id : $product;

        return Product::query()->lockForUpdate()->findOrFail($id);
    }
}
