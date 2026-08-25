<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_settings')) {
            return;
        }

        Schema::table('business_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_settings', 'sms_enabled')) {
                $table->boolean('sms_enabled')->default(false);
            }
            if (! Schema::hasColumn('business_settings', 'sms_provider')) {
                $table->string('sms_provider', 30)->default('beem');
            }
            if (! Schema::hasColumn('business_settings', 'sms_api_url')) {
                $table->string('sms_api_url')->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'sms_api_key')) {
                $table->text('sms_api_key')->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'sms_api_secret')) {
                $table->text('sms_api_secret')->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'sms_sender_id')) {
                $table->string('sms_sender_id', 40)->nullable();
            }
            if (! Schema::hasColumn('business_settings', 'sms_bearer_token')) {
                $table->text('sms_bearer_token')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_settings')) {
            return;
        }

        $columns = [
            'sms_enabled',
            'sms_provider',
            'sms_api_url',
            'sms_api_key',
            'sms_api_secret',
            'sms_sender_id',
            'sms_bearer_token',
        ];

        $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('business_settings', $column)));

        if ($existing !== []) {
            Schema::table('business_settings', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
