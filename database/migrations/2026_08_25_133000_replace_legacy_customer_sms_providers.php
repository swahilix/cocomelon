<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_settings') || ! Schema::hasColumn('business_settings', 'sms_provider')) {
            return;
        }

        $payload = [
            'sms_provider' => 'nextsms',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('business_settings', 'sms_enabled')) {
            $payload['sms_enabled'] = false;
        }
        if (Schema::hasColumn('business_settings', 'sms_api_url')) {
            $payload['sms_api_url'] = null;
        }
        if (Schema::hasColumn('business_settings', 'sms_api_key')) {
            $payload['sms_api_key'] = null;
        }
        if (Schema::hasColumn('business_settings', 'sms_api_secret')) {
            $payload['sms_api_secret'] = null;
        }
        if (Schema::hasColumn('business_settings', 'sms_sender_id')) {
            $payload['sms_sender_id'] = null;
        }
        if (Schema::hasColumn('business_settings', 'sms_bearer_token')) {
            $payload['sms_bearer_token'] = null;
        }

        DB::table('business_settings')
            ->whereNotIn('sms_provider', ['nextsms', 'momo'])
            ->update($payload);

        if (Schema::hasColumn('business_settings', 'sms_api_secret')) {
            DB::table('business_settings')->update(['sms_api_secret' => null]);
        }
        if (Schema::hasColumn('business_settings', 'sms_api_url')) {
            DB::table('business_settings')->update(['sms_api_url' => null]);
        }
    }

    public function down(): void
    {
        // Legacy Beem/custom credentials are intentionally not restored.
    }
};
