<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$migration = '2026_08_22_120000_create_stock_management_tables';

if (! Schema::hasTable('migrations')) {
    echo "Migration table does not exist yet; normal migration bootstrap will run.\n";
    exit(0);
}

if (DB::table('migrations')->where('migration', $migration)->exists()) {
    echo "Stock foundation migration already recorded.\n";
    exit(0);
}

$expected = [
    'stock_purchases' => ['id', 'reference', 'supplier_name', 'invoice_number', 'purchase_date', 'subtotal', 'additional_cost', 'total', 'payment_method', 'notes', 'created_by', 'created_at', 'updated_at'],
    'stock_purchase_items' => ['id', 'stock_purchase_id', 'product_id', 'quantity', 'unit_cost', 'line_total', 'created_at', 'updated_at'],
    'pos_sales' => ['id', 'reference', 'customer_name', 'customer_phone', 'subtotal', 'discount', 'total', 'amount_paid', 'change_due', 'payment_method', 'notes', 'created_by', 'created_at', 'updated_at'],
    'pos_sale_items' => ['id', 'pos_sale_id', 'product_id', 'product_name', 'sku', 'quantity', 'unit_price', 'unit_cost', 'line_total', 'created_at', 'updated_at'],
    'expenses' => ['id', 'reference', 'expense_date', 'category', 'description', 'amount', 'payment_method', 'payee', 'notes', 'created_by', 'created_at', 'updated_at'],
    'stock_movements' => ['id', 'product_id', 'direction', 'type', 'quantity', 'balance_after', 'unit_cost', 'reference', 'notes', 'created_by', 'created_at', 'updated_at'],
];

$present = array_values(array_filter(array_keys($expected), fn (string $table): bool => Schema::hasTable($table)));

if ($present === []) {
    echo "No legacy stock foundation tables detected; migration will run normally.\n";
    exit(0);
}

// Never guess how to repair an already-existing table. Existing tables must be
// compatible with the canonical foundation; only entirely missing tables are
// created by this reconciliation script.
$missingColumns = [];
foreach ($present as $table) {
    foreach ($expected[$table] as $column) {
        if (! Schema::hasColumn($table, $column)) {
            $missingColumns[] = $table.'.'.$column;
        }
    }
}

if ($missingColumns !== []) {
    fwrite(STDERR, "ERROR: existing legacy stock table is incompatible with the canonical foundation.\n");
    fwrite(STDERR, 'Missing required columns: '.implode(', ', $missingColumns)."\n");
    exit(2);
}

DB::transaction(function (): void {
    if (! Schema::hasTable('stock_purchases')) {
        Schema::create('stock_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number')->nullable()->index();
            $table->date('purchase_date')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('additional_cost', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('payment_method', 40)->default('cash');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        echo "Created missing table: stock_purchases\n";
    }

    if (! Schema::hasTable('stock_purchase_items')) {
        Schema::create('stock_purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });
        echo "Created missing table: stock_purchase_items\n";
    }

    if (! Schema::hasTable('pos_sales')) {
        Schema::create('pos_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('change_due', 15, 2)->default(0);
            $table->string('payment_method', 40)->default('cash');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
        echo "Created missing table: pos_sales\n";
    }

    if (! Schema::hasTable('pos_sale_items')) {
        Schema::create('pos_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('sku', 191)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });
        echo "Created missing table: pos_sale_items\n";
    }

    if (! Schema::hasTable('expenses')) {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->date('expense_date')->index();
            $table->string('category', 80)->index();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 40)->default('cash');
            $table->string('payee')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        echo "Created missing table: expenses\n";
    }

    if (! Schema::hasTable('stock_movements')) {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->enum('direction', ['in', 'out', 'adjustment'])->index();
            $table->string('type', 40)->index();
            $table->integer('quantity');
            $table->unsignedInteger('balance_after')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('reference', 80)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });
        echo "Created missing table: stock_movements\n";
    }
});

// Verify the completed foundation before touching migration history.
$problems = [];
foreach ($expected as $table => $columns) {
    if (! Schema::hasTable($table)) {
        $problems[] = 'missing table '.$table;
        continue;
    }

    foreach ($columns as $column) {
        if (! Schema::hasColumn($table, $column)) {
            $problems[] = 'missing column '.$table.'.'.$column;
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, "ERROR: stock foundation reconciliation did not complete safely.\n");
    fwrite(STDERR, implode("\n", $problems)."\n");
    exit(2);
}

$batch = max(1, (int) DB::table('migrations')->max('batch'));
DB::table('migrations')->insert([
    'migration' => $migration,
    'batch' => $batch,
]);

echo "Baselined reconciled stock foundation as {$migration}.\n";
