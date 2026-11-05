<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the two-step product entry flow:
 *  1. Product details (no prices or stock), with a structured warranty period.
 *  2. Opening stock or a purchase, each carrying cost, selling price and an
 *     optional customer discount, per product or per variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'warranty_duration_value')) {
                $table->unsignedSmallInteger('warranty_duration_value')->nullable();
            }
            if (! Schema::hasColumn('products', 'warranty_duration_unit')) {
                $table->string('warranty_duration_unit', 10)->nullable();
            }
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_movements', 'product_variant_id')) {
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            }
        });

        Schema::table('stock_purchase_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_purchase_items', 'product_variant_id')) {
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            }
            if (! Schema::hasColumn('stock_purchase_items', 'selling_price')) {
                $table->decimal('selling_price', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('stock_purchase_items', 'sale_price')) {
                $table->decimal('sale_price', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('stock_purchase_items', 'prices_applied_at')) {
                $table->timestamp('prices_applied_at')->nullable();
            }
        });

        Schema::table('stock_purchase_receipt_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_purchase_receipt_items', 'product_variant_id')) {
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            }
        });

        if (! Schema::hasTable('product_price_changes')) {
            Schema::create('product_price_changes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->decimal('old_price', 15, 2)->nullable();
                $table->decimal('new_price', 15, 2);
                $table->decimal('old_sale_price', 15, 2)->nullable();
                $table->decimal('new_sale_price', 15, 2)->nullable();
                $table->timestamp('sale_starts_at')->nullable();
                $table->timestamp('sale_ends_at')->nullable();
                $table->string('source', 30)->index();
                $table->string('reference', 80)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_changes');

        foreach ([
            'stock_purchase_receipt_items' => ['product_variant_id'],
            'stock_purchase_items' => ['product_variant_id', 'selling_price', 'sale_price', 'prices_applied_at'],
            'stock_movements' => ['product_variant_id'],
            'products' => ['warranty_duration_value', 'warranty_duration_unit'],
        ] as $tableName => $columns) {
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn($tableName, $column)));
            if ($existing !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($existing): void {
                    $table->dropColumn($existing);
                });
            }
        }
    }
};
