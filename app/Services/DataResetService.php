<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Clears transaction data (sales, online orders, stock, purchases, expenses,
 * installments, payments and cash registers) and/or permanently empties the
 * Trash, so the business can start entering data again. Products,
 * categories, brands, customers, suppliers, warranties, users, settings,
 * website content and selling prices are kept (unless they are in the Trash
 * and the Trash is emptied).
 */
class DataResetService
{
    /**
     * Tables cleared by the transaction reset, grouped for display. Child
     * tables come before their parents so rows are deleted in a safe order.
     *
     * @var array<string, array{label: string, tables: array<int, string>}>
     */
    public const GROUPS = [
        'sales' => ['label' => 'POS sales, returns, held sales, quotations and invoices', 'tables' => ['pos_sale_return_items', 'pos_sale_returns', 'pos_sale_items', 'pos_sales', 'pos_held_sales', 'pos_document_items', 'pos_documents']],
        'orders' => ['label' => 'Online orders', 'tables' => ['order_status_histories', 'order_items', 'orders']],
        'purchases' => ['label' => 'Purchases, supplier payments and returns, purchase orders', 'tables' => ['stock_purchase_return_items', 'stock_purchase_returns', 'stock_purchase_receipt_items', 'stock_purchase_receipts', 'stock_purchase_payments', 'stock_purchase_items', 'stock_purchases', 'purchase_order_items', 'purchase_orders']],
        'stock' => ['label' => 'Stock history and unit barcodes', 'tables' => ['stock_movements', 'product_units']],
        'expenses' => ['label' => 'Expenses', 'tables' => ['expenses']],
        'installments' => ['label' => 'Installment plans and their payments', 'tables' => ['installment_payments', 'installment_plan_items', 'installment_plans']],
        'registers' => ['label' => 'Cash register sessions and payment movements', 'tables' => ['cash_register_movements', 'cash_register_session_balances', 'cash_register_sessions']],
        'housekeeping' => ['label' => 'Duplicate-submission records and old import state', 'tables' => ['posting_guards', 'historical_import_states']],
    ];

    /**
     * Soft-deleted ("Trash") tables, in the order they are emptied: items that
     * use categories, brands and suppliers come before those.
     *
     * @var array<string, string>
     */
    public const TRASH = [
        'products' => 'Products',
        'product_variants' => 'Product variants',
        'services' => 'Services',
        'software_solutions' => 'Software',
        'product_reels' => 'Product reels',
        'hero_slides' => 'Homepage carousel slides',
        'buying_guides' => 'Buying guides',
        'categories' => 'Categories',
        'brands' => 'Brands',
        'suppliers' => 'Suppliers',
    ];

    /** Tables whose rows are product history: a product used here is never deleted. */
    private const PRODUCT_HISTORY = [
        'pos_sale_items', 'stock_purchase_items', 'stock_purchase_receipt_items', 'stock_movements', 'order_items',
        'installment_plan_items', 'purchase_order_items', 'pos_document_items', 'warranties',
    ];

    /** Tables whose rows are variant history. */
    private const VARIANT_HISTORY = ['stock_movements', 'stock_purchase_items', 'stock_purchase_receipt_items', 'purchase_order_items'];

    /** Rows that belong to a product and are removed with it. */
    private const PRODUCT_OWNED = [
        'product_variants', 'product_units', 'wishlists', 'product_reviews', 'product_activity_events',
        'product_contact_events', 'product_price_changes', 'coupon_product',
    ];

    /** Columns pointing at a product or service that are cleared when it is deleted. */
    private const NULL_ON_DELETE = [
        'product_id' => ['order_items', 'hero_slides', 'product_reels', 'merchant_sync_logs'],
        'service_id' => ['hero_slides', 'product_reels', 'service_inquiries'],
    ];

    /**
     * Columns reset to zero on rows that are kept.
     *
     * @var array<string, array<string, mixed>>
     */
    private const ZEROED = [
        'products' => ['stock_quantity' => 0, 'average_cost' => 0, 'last_purchase_cost' => 0],
        'product_variants' => ['stock_quantity' => 0],
        'suppliers' => ['opening_balance' => 0],
        'coupons' => ['usage_count' => 0],
    ];

    /**
     * Row counts that would be deleted or reset.
     */
    public function preview(): array
    {
        $groups = collect(self::GROUPS)->map(function (array $group, string $key): array {
            $tables = collect($group['tables'])
                ->filter(fn (string $table) => Schema::hasTable($table))
                ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

            return ['key' => $key, 'label' => $group['label'], 'rows' => (int) $tables->sum(), 'tables' => $tables->all()];
        })->values();

        return [
            'groups' => $groups->all(),
            'total_rows' => (int) $groups->sum('rows'),
            'reset' => [
                'products_with_stock' => $this->countWhere('products', 'stock_quantity', '!=', 0),
                'variants_with_stock' => $this->countWhere('product_variants', 'stock_quantity', '!=', 0),
                'suppliers_with_opening_balance' => $this->countWhere('suppliers', 'opening_balance', '!=', 0),
                'coupons_used' => $this->countWhere('coupons', 'usage_count', '>', 0),
            ],
            'trash' => $this->trashPreview(),
        ];
    }

    /**
     * Items in the Trash, and how many of them are still in use (and would be
     * kept) if the Trash were emptied without resetting transactions.
     */
    public function trashPreview(): array
    {
        $items = collect(self::TRASH)
            ->filter(fn (string $label, string $table) => $this->hasTrash($table))
            ->map(fn (string $label, string $table) => [
                'key' => $table,
                'label' => $label,
                'count' => DB::table($table)->whereNotNull('deleted_at')->count(),
                'in_use' => DB::table($table)->whereNotNull('deleted_at')->count() - $this->deletableTrashIds($table)->count(),
            ])
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values();

        return ['items' => $items->all(), 'total' => (int) $items->sum('count'), 'in_use' => (int) $items->sum('in_use')];
    }

    /**
     * Save every row that the reset or emptying the Trash would delete or
     * change to a JSON file. Returns the file name.
     */
    public function backup(?int $userId): string
    {
        $directory = self::backupDirectory();
        File::ensureDirectoryExists($directory, 0750);

        $name = 'data-reset-backup-'.now()->format('Y-m-d-His').'.json';
        $handle = fopen($directory.DIRECTORY_SEPARATOR.$name, 'w');
        if (! $handle) {
            throw new RuntimeException('Could not create the backup file. Check that the storage folder is writable.');
        }

        fwrite($handle, '{"created_at":'.json_encode(now()->toIso8601String()).',"created_by":'.json_encode($userId).',"app":'.json_encode(config('app.name')));

        // Transaction tables (all rows) and kept rows whose values are zeroed.
        $groupTables = collect(self::GROUPS)->pluck('tables')->flatten();
        $tables = [];
        foreach ($groupTables as $table) {
            $tables[$table] = fn () => DB::table($table);
        }
        foreach (self::ZEROED as $table => $values) {
            $tables[$table] = fn () => DB::table($table)->select(array_values(array_filter(['id', ...array_keys($values)], fn ($column) => Schema::hasColumn($table, $column))));
        }
        $this->writeSection($handle, 'tables', $tables);

        // Trash: soft-deleted rows, plus rows owned by trashed products.
        $trash = [];
        foreach (array_keys(self::TRASH) as $table) {
            if ($this->hasTrash($table)) {
                $trash[$table] = fn () => DB::table($table)->whereNotNull('deleted_at');
            }
        }
        $trashedProducts = $this->hasTrash('products') ? DB::table('products')->whereNotNull('deleted_at')->pluck('id')->all() : [];
        foreach (self::PRODUCT_OWNED as $table) {
            if ($table !== 'product_variants' && $trashedProducts !== []) {
                $trash['product_owned.'.$table] = fn () => DB::table($table)->whereIn('product_id', $trashedProducts);
            }
        }
        if ($trashedProducts !== [] && Schema::hasTable('product_variants')) {
            $trash['product_owned.product_variants'] = fn () => DB::table('product_variants')->whereIn('product_id', $trashedProducts);
        }
        $this->writeSection($handle, 'trash', $trash);

        fwrite($handle, '}');
        fclose($handle);

        return $name;
    }

    /**
     * Reset transactions and/or empty the Trash, as one database transaction:
     * either everything selected is done or nothing is.
     *
     * @return array{deleted: array<string, int>, trash: array<string, array{deleted: int, kept: int}>}
     */
    public function run(bool $resetTransactions, bool $emptyTrash): array
    {
        $deleted = [];
        $trash = [];

        DB::transaction(function () use ($resetTransactions, $emptyTrash, &$deleted, &$trash): void {
            if ($resetTransactions) {
                $deleted = $this->resetTransactions();
            }

            if ($emptyTrash) {
                $trash = $this->emptyTrash();
            }
        });

        return ['deleted' => $deleted, 'trash' => $trash];
    }

    /**
     * Delete transaction data and zero stock and costs.
     *
     * @return array<string, int> rows deleted per table
     */
    public function reset(): array
    {
        return $this->run(true, false)['deleted'];
    }

    public static function backupDirectory(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'backups');
    }

    /**
     * @return array<int, array{name: string, size: int, created_at: string}>
     */
    public static function backups(): array
    {
        $directory = self::backupDirectory();
        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'data-reset-backup-') && $file->getExtension() === 'json')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take(10)
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date('c', $file->getMTime()),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function resetTransactions(): array
    {
        $deleted = [];

        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::GROUPS as $group) {
                foreach ($group['tables'] as $table) {
                    if (Schema::hasTable($table)) {
                        $deleted[$table] = DB::table($table)->delete();
                    }
                }
            }

            foreach (self::ZEROED as $table => $values) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $values = array_filter($values, fn ($value, string $column) => Schema::hasColumn($table, $column), ARRAY_FILTER_USE_BOTH);
                if ($values !== []) {
                    DB::table($table)->update($values);
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $deleted;
    }

    /**
     * Permanently delete Trash items that nothing else uses.
     *
     * @return array<string, array{deleted: int, kept: int}>
     */
    private function emptyTrash(): array
    {
        $result = [];

        foreach (array_keys(self::TRASH) as $table) {
            if (! $this->hasTrash($table)) {
                continue;
            }

            $total = DB::table($table)->whereNotNull('deleted_at')->count();
            $ids = $this->deletableTrashIds($table)->all();

            foreach (array_chunk($ids, 500) as $chunk) {
                $this->deleteWithDependents($table, $chunk);
            }

            if ($total > 0) {
                $result[$table] = ['deleted' => count($ids), 'kept' => $total - count($ids)];
            }
        }

        return $result;
    }

    /**
     * Ids of trashed rows in a table that are not used anywhere and can be
     * deleted permanently.
     */
    private function deletableTrashIds(string $table): Collection
    {
        $query = DB::table($table)->whereNotNull("{$table}.deleted_at");

        match ($table) {
            'products' => $this->whereUnused($query, $table, 'product_id', self::PRODUCT_HISTORY),
            'product_variants' => $this->whereUnused($query, $table, 'product_variant_id', self::VARIANT_HISTORY),
            'categories' => $this->whereUnused($query, $table, 'category_id', ['products', 'services', 'buying_guides']),
            'brands' => $this->whereUnused($query, $table, 'brand_id', ['products']),
            'suppliers' => $this->whereUnused($query, $table, 'supplier_id', ['stock_purchases', 'purchase_orders']),
            default => null,
        };

        return $query->pluck("{$table}.id");
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function whereUnused(Builder $query, string $table, string $column, array $tables): void
    {
        foreach ($tables as $usedIn) {
            if (Schema::hasTable($usedIn) && Schema::hasColumn($usedIn, $column)) {
                $query->whereNotExists(fn ($exists) => $exists->select(DB::raw(1))->from($usedIn)->whereColumn("{$usedIn}.{$column}", "{$table}.id"));
            }
        }
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function deleteWithDependents(string $table, array $ids): void
    {
        if ($table === 'products') {
            foreach (self::PRODUCT_OWNED as $owned) {
                if (Schema::hasTable($owned) && Schema::hasColumn($owned, 'product_id')) {
                    DB::table($owned)->whereIn('product_id', $ids)->delete();
                }
            }
            $this->nullReferences('product_id', $ids);
        }

        if ($table === 'services') {
            $this->nullReferences('service_id', $ids);
        }

        if ($table === 'categories' && Schema::hasTable('coupon_category')) {
            DB::table('coupon_category')->whereIn('category_id', $ids)->delete();
        }

        if ($table === 'brands' && Schema::hasTable('coupon_brand')) {
            DB::table('coupon_brand')->whereIn('brand_id', $ids)->delete();
        }

        DB::table($table)->whereIn('id', $ids)->delete();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function nullReferences(string $column, array $ids): void
    {
        foreach (self::NULL_ON_DELETE[$column] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->whereIn($column, $ids)->update([$column => null]);
            }
        }
    }

    /**
     * @param  resource  $handle
     * @param  array<string, callable(): Builder>  $queries
     */
    private function writeSection($handle, string $section, array $queries): void
    {
        fwrite($handle, ','.json_encode($section).':{');
        $first = true;

        foreach ($queries as $key => $makeQuery) {
            $table = str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key;
            if (! Schema::hasTable($table)) {
                continue;
            }

            fwrite($handle, ($first ? '' : ',').json_encode($key).':[');
            $first = false;
            $firstRow = true;

            $query = $makeQuery();
            $cursor = Schema::hasColumn($table, 'id') ? $query->lazyById(500) : $query->cursor();

            foreach ($cursor as $row) {
                fwrite($handle, ($firstRow ? '' : ',').json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
                $firstRow = false;
            }

            fwrite($handle, ']');
        }

        fwrite($handle, '}');
    }

    private function hasTrash(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at');
    }

    private function countWhere(string $table, string $column, string $operator, mixed $value): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->where($column, $operator, $value)->count()
            : 0;
    }
}
