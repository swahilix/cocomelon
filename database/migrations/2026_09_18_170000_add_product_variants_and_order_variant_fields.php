<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('sku', 100)->unique();
                $table->string('label', 140)->nullable();
                $table->string('color', 100)->nullable()->index();
                $table->string('storage', 100)->nullable()->index();
                $table->string('size', 100)->nullable()->index();
                $table->decimal('price', 15, 2)->nullable();
                $table->decimal('sale_price', 15, 2)->nullable();
                $table->unsignedInteger('stock_quantity')->default(0);
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['product_id', 'is_active', 'sort_order'], 'product_variant_public_index');
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'variant_id')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('variant_id')->nullable()->after('product_id')->index();
                $table->string('variant_name', 190)->nullable()->after('product_name');
                $table->string('variant_sku', 100)->nullable()->after('sku');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'variant_id')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropColumn(['variant_id', 'variant_name', 'variant_sku']);
            });
        }

        Schema::dropIfExists('product_variants');
    }
};
