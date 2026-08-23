<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('business_name')->nullable();
                $table->string('legal_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 80)->nullable();
                $table->string('secondary_phone', 80)->nullable();
                $table->string('whatsapp', 80)->nullable();
                $table->string('address')->nullable();
                $table->string('city', 120)->nullable();
                $table->string('region', 120)->nullable();
                $table->string('country', 120)->nullable();
                $table->string('working_hours')->nullable();
                $table->text('description')->nullable();
                $table->string('facebook_url')->nullable();
                $table->string('instagram_url')->nullable();
                $table->string('tiktok_url')->nullable();
                $table->string('youtube_url')->nullable();
                $table->string('x_url')->nullable();
                $table->string('maps_url')->nullable();
                $table->timestamps();
            });
        }

        if (! DB::table('business_settings')->where('id', 1)->exists()) {
            DB::table('business_settings')->insert([
                'id' => 1,
                'business_name' => config('app.name', 'Prinx Electronics'),
                'legal_name' => config('store.legal_name'),
                'email' => config('store.email'),
                'phone' => config('store.phone'),
                'secondary_phone' => null,
                'whatsapp' => config('store.phone'),
                'address' => config('store.address'),
                'city' => null,
                'region' => null,
                'country' => config('store.country'),
                'working_hours' => null,
                'description' => config('store.description'),
                'facebook_url' => null,
                'instagram_url' => null,
                'tiktok_url' => null,
                'youtube_url' => null,
                'x_url' => null,
                'maps_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
