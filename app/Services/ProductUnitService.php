<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductUnitService
{
    public function syncToStock(Product|int $product): Collection
    {
        $productModel = $product instanceof Product
            ? $product
            : Product::query()->findOrFail($product);

        if (! $productModel->track_stock) {
            return collect();
        }

        return DB::transaction(function () use ($productModel): Collection {
            $locked = Product::query()->lockForUpdate()->findOrFail($productModel->id);
            $target = max(0, (int) $locked->stock_quantity);
            $available = DB::table('product_units')
                ->where('product_id', $locked->id)
                ->where('status', 'available')
                ->lockForUpdate()
                ->get();

            if ($available->count() < $target) {
                $this->createUnitsLocked($locked->id, $target - $available->count(), 'stock_sync', null, (float) $locked->average_cost);
            } elseif ($available->count() > $target) {
                $remove = $available
                    ->sortBy(fn ($unit) => [$unit->last_printed_at !== null ? 1 : 0, -1 * (int) $unit->id])
                    ->take($available->count() - $target)
                    ->pluck('id');

                if ($remove->isNotEmpty()) {
                    DB::table('product_units')->whereIn('id', $remove)->update([
                        'status' => 'removed',
                        'removed_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $this->availableUnits($locked->id);
        });
    }

    public function availableUnits(int $productId): Collection
    {
        return DB::table('product_units')
            ->where('product_id', $productId)
            ->where('status', 'available')
            ->orderBy('unit_number')
            ->get();
    }

    public function findAvailableBarcode(string $barcode): ?object
    {
        return DB::table('product_units')
            ->join('products', 'products.id', '=', 'product_units.product_id')
            ->where('product_units.barcode', strtoupper(trim($barcode)))
            ->where('product_units.status', 'available')
            ->whereNull('products.deleted_at')
            ->where('products.status', 'active')
            ->select(
                'product_units.id as unit_id',
                'product_units.barcode',
                'product_units.unit_number',
                'products.id as product_id',
                'products.name',
                'products.sku',
                'products.stock_quantity',
                'products.track_stock',
                'products.price',
                'products.sale_price',
                'products.sale_starts_at',
                'products.sale_ends_at',
            )
            ->first();
    }

    public function consumeForSale(Product $product, int $quantity, array $requestedBarcodes, int $saleId, int $saleItemId): Collection
    {
        if (! $product->track_stock || $quantity <= 0) {
            return collect();
        }

        $this->syncToStock($product);

        $requested = collect($requestedBarcodes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        if ($requested->count() > $quantity) {
            throw new RuntimeException("More unit barcodes were supplied than the quantity sold for {$product->name}.");
        }

        $selected = collect();
        if ($requested->isNotEmpty()) {
            $selected = DB::table('product_units')
                ->where('product_id', $product->id)
                ->whereIn('barcode', $requested)
                ->lockForUpdate()
                ->get();

            if ($selected->count() !== $requested->count()) {
                throw new RuntimeException("One or more scanned barcodes do not belong to {$product->name}.");
            }

            $unavailable = $selected->first(fn ($unit) => $unit->status !== 'available');
            if ($unavailable) {
                throw new RuntimeException("Barcode {$unavailable->barcode} has already been sold or is no longer available.");
            }
        }

        $remaining = $quantity - $selected->count();
        if ($remaining > 0) {
            $extra = DB::table('product_units')
                ->where('product_id', $product->id)
                ->where('status', 'available')
                ->when($requested->isNotEmpty(), fn ($query) => $query->whereNotIn('barcode', $requested))
                ->orderBy('unit_number')
                ->lockForUpdate()
                ->limit($remaining)
                ->get();

            if ($extra->count() !== $remaining) {
                throw new RuntimeException("Not enough unit barcodes are available for {$product->name}.");
            }
            $selected = $selected->concat($extra);
        }

        DB::table('product_units')->whereIn('id', $selected->pluck('id'))->update([
            'status' => 'sold',
            'pos_sale_id' => $saleId,
            'pos_sale_item_id' => $saleItemId,
            'sold_at' => now(),
            'returned_at' => null,
            'removed_at' => null,
            'updated_at' => now(),
        ]);

        return $selected->values();
    }

    public function restoreSoldUnits(int $saleItemId, int $quantity): Collection
    {
        if ($quantity <= 0) return collect();

        $units = DB::table('product_units')
            ->where('pos_sale_item_id', $saleItemId)
            ->where('status', 'sold')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->limit($quantity)
            ->get();

        if ($units->isNotEmpty()) {
            DB::table('product_units')->whereIn('id', $units->pluck('id'))->update([
                'status' => 'available',
                'sold_at' => null,
                'returned_at' => now(),
                'removed_at' => null,
                'updated_at' => now(),
            ]);
        }

        return $units;
    }

    public function restoreSale(int $saleId): void
    {
        DB::table('product_units')
            ->where('pos_sale_id', $saleId)
            ->where('status', 'sold')
            ->update([
                'status' => 'available',
                'sold_at' => null,
                'returned_at' => now(),
                'removed_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function markPrinted(array $unitIds): void
    {
        $ids = collect($unitIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) return;

        DB::table('product_units')->whereIn('id', $ids)->where('status', 'available')->update([
            'last_printed_at' => now(),
            'print_count' => DB::raw('print_count + 1'),
            'updated_at' => now(),
        ]);
    }

    private function createUnitsLocked(int $productId, int $quantity, string $sourceType, ?int $sourceId, ?float $unitCost): void
    {
        if ($quantity <= 0) return;

        $next = (int) DB::table('product_units')->where('product_id', $productId)->max('unit_number') + 1;
        $now = now();
        $rows = [];

        for ($offset = 0; $offset < $quantity; $offset++) {
            $number = $next + $offset;
            $rows[] = [
                'product_id' => $productId,
                'unit_number' => $number,
                'barcode' => 'PX-'.$productId.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'status' => 'available',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'unit_cost' => $unitCost,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_units')->insert($chunk);
        }
    }
}
