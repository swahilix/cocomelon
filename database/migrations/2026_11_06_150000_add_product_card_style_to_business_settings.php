<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which product card design the storefront uses (shop page, homepage sections
 * and wishlist). Chosen in Admin → Settings → Storefront appearance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_settings') && ! Schema::hasColumn('business_settings', 'product_card_style')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->string('product_card_style', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'product_card_style')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->dropColumn('product_card_style');
            });
        }
    }
};
