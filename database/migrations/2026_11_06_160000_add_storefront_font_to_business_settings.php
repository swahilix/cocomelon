<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The storefront font, chosen in Admin → Settings → Storefront appearance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_settings') && ! Schema::hasColumn('business_settings', 'storefront_font')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->string('storefront_font', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'storefront_font')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->dropColumn('storefront_font');
            });
        }
    }
};
