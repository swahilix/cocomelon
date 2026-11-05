<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Receives goods on a stock purchase: adds stock (to the variant when the line
 * is for a variant), updates average cost, and applies the line's selling
 * price and customer discount the first time that line is received.
 */
class PurchaseReceivingService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ProductPricingService $pricing,
    ) {
    }

    /**
     * @param  array<int, array{purchase_item_id: int, quantity: int}>  $lines
     */
    public function receive(int $purchaseId, array $lines, string $receivedDate, ?string $notes, ?int $userId): string
    {
        return DB::transaction(function () use ($purchaseId, $lines, $receivedDate, $notes, $userId): string {
            $record = DB::table('stock_purchases')->where('id', $purchaseId)->lockForUpdate()->first();
            if (! $record) throw new RuntimeException('Purchase was not found.');
            if ($record->status === 'cancelled') throw new RuntimeException('A cancelled purchase cannot receive stock.');
            if ($record->status === 'received') throw new RuntimeException('This purchase has already been fully received.');

            $selected = collect($lines)->filter(fn ($line) => (int) ($line['quantity'] ?? 0) > 0);
            if ($selected->isEmpty()) throw new RuntimeException('Enter at least one quantity to receive.');

            $receiptReference = 'RCV-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
            $receiptId = DB::table('stock_purchase_receipts')->insertGetId([
                'stock_purchase_id' => $purchaseId,
                'reference' => $receiptReference,
                'received_date' => $receivedDate,
                'notes' => $notes,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($selected as $submitted) {
                $item = DB::table('stock_purchase_items')
                    ->where('id', $submitted['purchase_item_id'])
                    ->where('stock_purchase_id', $purchaseId)
                    ->lockForUpdate()
                    ->first();
                if (! $item) throw new RuntimeException('One of the purchase items was not found.');

                $quantity = (int) $submitted['quantity'];
                $remaining = (int) $item->quantity - (int) $item->quantity_received;
                if ($quantity > $remaining) {
                    throw new RuntimeException("Cannot receive {$quantity} units. Only {$remaining} remain for this item.");
                }

                $product = Product::query()->findOrFail($item->product_id);
                $variant = $this->variantFor($item, $product);

                $receiptItem = [
                    'stock_purchase_receipt_id' => $receiptId,
                    'stock_purchase_item_id' => $item->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (property_exists($item, 'product_variant_id')) {
                    $receiptItem['product_variant_id'] = $variant?->id;
                }
                DB::table('stock_purchase_receipt_items')->insert($receiptItem);

                DB::table('stock_purchase_items')->where('id', $item->id)->update([
                    'quantity_received' => (int) $item->quantity_received + $quantity,
                    'updated_at' => now(),
                ]);

                $this->inventory->changeStock(
                    $product,
                    $variant,
                    $quantity,
                    'purchase_receipt',
                    $receiptReference,
                    (float) $item->unit_cost,
                    $record->supplier_name,
                    $userId,
                    'stock_purchase_receipt',
                    $receiptId,
                    'Purchase stock received',
                );

                $this->applyPrices($item, $product, $variant, $record->reference, $userId);
            }

            $items = DB::table('stock_purchase_items')->where('stock_purchase_id', $purchaseId)->get();
            $allReceived = $items->every(fn ($item) => (int) $item->quantity_received >= (int) $item->quantity);

            DB::table('stock_purchases')->where('id', $purchaseId)->update([
                'status' => $allReceived ? 'received' : 'partially_received',
                'received_date' => $allReceived ? $receivedDate : null,
                'updated_at' => now(),
            ]);

            return $receiptReference;
        });
    }

    /**
     * Receive every outstanding quantity on the purchase.
     */
    public function receiveAll(int $purchaseId, string $receivedDate, ?string $notes, ?int $userId): string
    {
        $lines = DB::table('stock_purchase_items')
            ->where('stock_purchase_id', $purchaseId)
            ->get()
            ->map(fn ($item) => [
                'purchase_item_id' => (int) $item->id,
                'quantity' => max(0, (int) $item->quantity - (int) $item->quantity_received),
            ])
            ->all();

        return $this->receive($purchaseId, $lines, $receivedDate, $notes, $userId);
    }

    public function variantFor(object $item, Product $product): ?ProductVariant
    {
        $variantId = (int) ($item->product_variant_id ?? 0);
        if ($variantId <= 0) {
            return null;
        }

        $variant = ProductVariant::withTrashed()->find($variantId);
        if (! $variant || (int) $variant->product_id !== (int) $product->id) {
            throw new RuntimeException('A purchase line refers to a variant that no longer belongs to its product.');
        }

        return $variant;
    }

    private function applyPrices(object $item, Product $product, ?ProductVariant $variant, string $reference, ?int $userId): void
    {
        if (($item->selling_price ?? null) === null || ($item->prices_applied_at ?? null) !== null) {
            return;
        }

        $this->pricing->apply(
            $product,
            $variant,
            (float) $item->selling_price,
            $item->sale_price !== null ? (float) $item->sale_price : null,
            'purchase',
            $reference,
            $userId,
        );

        DB::table('stock_purchase_items')->where('id', $item->id)->update([
            'prices_applied_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
