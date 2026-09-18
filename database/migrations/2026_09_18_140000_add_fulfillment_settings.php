<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_settings') && ! Schema::hasColumn('business_settings', 'delivery_enabled')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->boolean('delivery_enabled')->default(true);
                $table->string('delivery_area')->nullable();
                $table->string('delivery_eta', 120)->nullable();
                $table->boolean('pickup_enabled')->default(true);
                $table->string('pickup_location')->nullable();
                $table->string('pickup_instructions')->nullable();
            });

            $settings = DB::table('business_settings')->where('id', 1)->first();
            if ($settings) {
                DB::table('business_settings')->where('id', 1)->update([
                    'delivery_area' => $settings->city ?: $settings->region,
                    'delivery_eta' => '20–40 minutes for selected local orders',
                    'pickup_location' => $settings->address,
                    'pickup_instructions' => 'Pickup is available after order confirmation during business hours.',
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'fulfillment_method')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('fulfillment_method', 20)->default('delivery')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'fulfillment_method')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('fulfillment_method'));
        }

        if (Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'delivery_enabled')) {
            Schema::table('business_settings', function (Blueprint $table): void {
                $table->dropColumn([
                    'delivery_enabled',
                    'delivery_area',
                    'delivery_eta',
                    'pickup_enabled',
                    'pickup_location',
                    'pickup_instructions',
                ]);
            });
        }
    }
};
