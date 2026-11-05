<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI and barcode lookup settings managed from the admin panel. Empty values
 * fall back to the server .env configuration. Keys are stored encrypted.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'openai_api_key',
        'openai_model',
        'ai_product_intelligence_enabled',
        'ai_description_formatter_enabled',
        'upcitemdb_enabled',
        'upcitemdb_key',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('business_settings')) {
            return;
        }

        Schema::table('business_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_settings', 'openai_api_key')) {
                $table->text('openai_api_key')->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'openai_model')) {
                $table->string('openai_model', 100)->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'ai_product_intelligence_enabled')) {
                $table->boolean('ai_product_intelligence_enabled')->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'ai_description_formatter_enabled')) {
                $table->boolean('ai_description_formatter_enabled')->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'upcitemdb_enabled')) {
                $table->boolean('upcitemdb_enabled')->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'upcitemdb_key')) {
                $table->text('upcitemdb_key')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_settings')) {
            return;
        }

        $existing = array_values(array_filter(self::COLUMNS, fn (string $column): bool => Schema::hasColumn('business_settings', $column)));
        if ($existing !== []) {
            Schema::table('business_settings', function (Blueprint $table) use ($existing): void {
                $table->dropColumn($existing);
            });
        }
    }
};
