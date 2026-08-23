<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_settings') && ! Schema::hasColumn('business_settings', 'payment_strip')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->string('payment_strip')->nullable()->after('maps_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'payment_strip')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->dropColumn('payment_strip');
            });
        }
    }
};
