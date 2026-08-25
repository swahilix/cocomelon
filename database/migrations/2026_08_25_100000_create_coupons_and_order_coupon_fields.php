<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL can leave tables behind when a migration fails while adding a
        // foreign key. Clean up the known coupon tables first so a retry after a
        // failed deployment can run from a consistent state.
        Schema::dropIfExists('coupon_brand');
        Schema::dropIfExists('coupon_product');
        Schema::dropIfExists('coupon_category');
        Schema::dropIfExists('coupons');

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 64)->unique();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->string('applies_to', 30)->default('all');
            $table->decimal('minimum_order_amount', 12, 2)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('coupon_category', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            // Do not constrain catalog IDs here. Some existing production
            // installations use legacy integer key definitions for these tables,
            // while newer installs use bigint IDs. The application relationship
            // works with either representation.
            $table->unsignedBigInteger('category_id');
            $table->primary(['coupon_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('coupon_product', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->primary(['coupon_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('coupon_brand', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->unsignedBigInteger('brand_id');
            $table->primary(['coupon_id', 'brand_id']);
            $table->index('brand_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->nullable()->after('user_id')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 64)->nullable()->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('coupon_code');
        });

        Schema::dropIfExists('coupon_brand');
        Schema::dropIfExists('coupon_product');
        Schema::dropIfExists('coupon_category');
        Schema::dropIfExists('coupons');
    }
};
