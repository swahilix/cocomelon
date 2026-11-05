<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders: a list of items and quantities to buy, printed or exported
 * without prices, with an internal cost estimate. Converting one creates a
 * stock purchase linked back to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table): void {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->string('supplier_name', 255)->nullable();
                $table->date('order_date');
                $table->date('expected_date')->nullable();
                $table->string('status', 20)->default('draft')->index();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('stock_purchase_id')->nullable()->index();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->string('product_name', 190);
                $table->string('variant_name', 190)->nullable();
                $table->string('sku', 190)->nullable();
                $table->unsignedInteger('quantity');
                $table->decimal('estimated_unit_cost', 15, 2)->nullable();
                $table->string('notes', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('stock_purchases') && ! Schema::hasColumn('stock_purchases', 'purchase_order_id')) {
            Schema::table('stock_purchases', function (Blueprint $table): void {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_purchases') && Schema::hasColumn('stock_purchases', 'purchase_order_id')) {
            Schema::table('stock_purchases', function (Blueprint $table): void {
                $table->dropColumn('purchase_order_id');
            });
        }

        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
