<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Clears transaction data (sales, online orders, stock, purchases, expenses,
 * installments, payments and cash registers) so the business can start
 * entering data again. Products, categories, brands, customers, suppliers,
 * warranties, users, settings, website content and selling prices are kept.
 */
class DataResetService
{
    /**
     * Tables cleared by the reset, grouped for display. Child tables come
     * before their parents so rows are deleted in a safe order.
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
     * Row counts that would be deleted or reset, per group.
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
        ];
    }

    /**
     * Save every row that the reset would delete or change to a JSON file.
     * Returns the file name (inside the private backups folder).
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

        fwrite($handle, '{"created_at":'.json_encode(now()->toIso8601String()).',"created_by":'.json_encode($userId).',"app":'.json_encode(config('app.name')).',"tables":{');

        $first = true;
        $tables = collect(self::GROUPS)->pluck('tables')->flatten()->merge(array_keys(self::ZEROED))->unique();

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            fwrite($handle, ($first ? '' : ',').json_encode($table).':[');
            $first = false;
            $firstRow = true;

            $columns = isset(self::ZEROED[$table]) && ! in_array($table, collect(self::GROUPS)->pluck('tables')->flatten()->all(), true)
                ? array_values(array_filter(['id', ...array_keys(self::ZEROED[$table])], fn ($column) => Schema::hasColumn($table, $column)))
                : ['*'];

            $query = DB::table($table)->select($columns);
            $cursor = Schema::hasColumn($table, 'id') ? $query->lazyById(500) : $query->cursor();

            foreach ($cursor as $row) {
                fwrite($handle, ($firstRow ? '' : ',').json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
                $firstRow = false;
            }

            fwrite($handle, ']');
        }

        fwrite($handle, '}}');
        fclose($handle);

        return $name;
    }

    /**
     * Delete transaction data and zero stock and costs. Runs as a single
     * database transaction: either everything is reset or nothing is.
     *
     * @return array<string, int> rows deleted per table
     */
    public function reset(): array
    {
        $deleted = [];

        Schema::withoutForeignKeyConstraints(function () use (&$deleted): void {
            DB::transaction(function () use (&$deleted): void {
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
            });
        });

        return $deleted;
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

    private function countWhere(string $table, string $column, string $operator, mixed $value): int
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->where($column, $operator, $value)->count()
            : 0;
    }
}
