<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug', 191)->unique();
            $table->string('type', 20)->default('product')->index();
            $table->text('description')->nullable(); $table->string('image')->nullable();
            $table->boolean('is_active')->default(true)->index(); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug', 191)->unique();
            $table->string('logo')->nullable(); $table->boolean('is_active')->default(true)->index(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); $table->string('slug', 191)->unique(); $table->string('sku', 191)->unique();
            $table->text('short_description')->nullable(); $table->longText('description');
            $table->decimal('price', 15, 2); $table->decimal('sale_price', 15, 2)->nullable();
            $table->timestamp('sale_starts_at')->nullable(); $table->timestamp('sale_ends_at')->nullable();
            $table->unsignedInteger('stock_quantity')->default(0); $table->boolean('track_stock')->default(true);
            $table->enum('condition', ['new','refurbished','used'])->default('new');
            $table->enum('status', ['draft','active','archived'])->default('draft')->index();
            $table->boolean('featured')->default(false)->index();
            $table->boolean('merchant_sync_enabled')->default(true)->index();
            $table->string('primary_image')->nullable(); $table->json('gallery')->nullable();
            $table->string('gtin', 191)->nullable()->index(); $table->string('mpn', 191)->nullable()->index();
            $table->string('google_product_category')->nullable(); $table->string('product_type')->nullable();
            $table->string('item_group_id', 191)->nullable()->index(); $table->string('color')->nullable();
            $table->string('size')->nullable(); $table->string('gender')->nullable(); $table->string('age_group')->nullable();
            $table->string('video_url')->nullable();
            $table->decimal('shipping_price', 15, 2)->nullable(); $table->decimal('shipping_weight', 10, 3)->nullable();
            $table->string('warranty')->nullable(); $table->string('meta_title')->nullable();
            $table->string('meta_description', 180)->nullable();
            $table->timestamp('merchant_last_synced_at')->nullable()->index();
            $table->enum('merchant_sync_status', ['never','pending','synced','failed'])->default('never')->index();
            $table->text('merchant_sync_error')->nullable(); $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps(); $table->softDeletes();
            $table->index(['status','featured','sort_order']);
        });
    }
    public function down(): void { Schema::dropIfExists('products'); Schema::dropIfExists('brands'); Schema::dropIfExists('categories'); }
};
