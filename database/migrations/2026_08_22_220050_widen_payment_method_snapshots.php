<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_sales') && Schema::hasColumn('pos_sales', 'payment_method')) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->string('payment_method', 120)->change();
            });
        }

        if (Schema::hasTable('pos_held_sales') && Schema::hasColumn('pos_held_sales', 'payment_method')) {
            Schema::table('pos_held_sales', function (Blueprint $table): void {
                $table->string('payment_method', 120)->change();
            });
        }

        if (Schema::hasTable('pos_sale_returns') && Schema::hasColumn('pos_sale_returns', 'refund_method')) {
            Schema::table('pos_sale_returns', function (Blueprint $table): void {
                $table->string('refund_method', 120)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_sales') && Schema::hasColumn('pos_sales', 'payment_method')) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->string('payment_method', 40)->change();
            });
        }

        if (Schema::hasTable('pos_held_sales') && Schema::hasColumn('pos_held_sales', 'payment_method')) {
            Schema::table('pos_held_sales', function (Blueprint $table): void {
                $table->string('payment_method', 40)->change();
            });
        }

        if (Schema::hasTable('pos_sale_returns') && Schema::hasColumn('pos_sale_returns', 'refund_method')) {
            Schema::table('pos_sale_returns', function (Blueprint $table): void {
                $table->string('refund_method', 40)->change();
            });
        }
    }
};
