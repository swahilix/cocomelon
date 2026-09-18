<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_settings') || Schema::hasColumn('business_settings', 'payment_mobile_money_enabled')) {
            return;
        }

        Schema::table('business_settings', function (Blueprint $table): void {
            $table->boolean('payment_mobile_money_enabled')->default(true);
            $table->boolean('payment_bank_transfer_enabled')->default(true);
            $table->boolean('payment_cash_on_delivery_enabled')->default(true);
            $table->string('payment_mobile_money_description')->nullable();
            $table->string('payment_bank_transfer_description')->nullable();
            $table->string('payment_cash_on_delivery_description')->nullable();
            $table->string('payment_safety_note')->nullable();
            $table->boolean('payment_strip_visible')->default(false);
        });

        if (DB::table('business_settings')->where('id', 1)->exists()) {
            DB::table('business_settings')->where('id', 1)->update([
                'payment_mobile_money_description' => 'Pay using the mobile money instructions provided for your order.',
                'payment_bank_transfer_description' => 'Pay by bank transfer using the confirmed order instructions.',
                'payment_cash_on_delivery_description' => 'Pay in cash when your order is delivered or collected.',
                'payment_safety_note' => 'Prinx Tech will never ask for your mobile-money PIN, password or OTP.',
                'payment_strip_visible' => false,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_settings') || ! Schema::hasColumn('business_settings', 'payment_mobile_money_enabled')) {
            return;
        }

        Schema::table('business_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_mobile_money_enabled',
                'payment_bank_transfer_enabled',
                'payment_cash_on_delivery_enabled',
                'payment_mobile_money_description',
                'payment_bank_transfer_description',
                'payment_cash_on_delivery_description',
                'payment_safety_note',
                'payment_strip_visible',
            ]);
        });
    }
};
