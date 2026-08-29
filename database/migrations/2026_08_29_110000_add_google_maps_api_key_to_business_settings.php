<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_settings') && ! Schema::hasColumn('business_settings', 'google_maps_api_key')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->string('google_maps_api_key')->nullable()->after('maps_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'google_maps_api_key')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->dropColumn('google_maps_api_key');
            });
        }
    }
};
