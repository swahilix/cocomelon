<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_purchases', 'is_historical')) {
            Schema::table('stock_purchases', function (Blueprint $table): void {
                $table->boolean('is_historical')->default(false)->after('payment_status')->index();
                $table->timestamp('historical_imported_at')->nullable()->after('is_historical');
            });
        }

        if (! Schema::hasColumn('pos_sales', 'is_historical')) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->boolean('is_historical')->default(false)->after('status')->index();
                $table->timestamp('historical_imported_at')->nullable()->after('is_historical');
            });
        }

        if (! Schema::hasColumn('stock_movements', 'affects_stock')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->boolean('affects_stock')->default(true)->after('quantity')->index();
                $table->boolean('is_historical')->default(false)->after('affects_stock')->index();
            });
        }

        if (! Schema::hasTable('historical_import_states')) {
            Schema::create('historical_import_states', function (Blueprint $table): void {
                $table->id();
                $table->timestamp('purchases_confirmed_at')->nullable();
                $table->unsignedBigInteger('purchases_confirmed_by')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_import_states');

        if (Schema::hasColumn('stock_movements', 'affects_stock')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropColumn(['affects_stock', 'is_historical']);
            });
        }

        if (Schema::hasColumn('pos_sales', 'is_historical')) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->dropColumn(['is_historical', 'historical_imported_at']);
            });
        }

        if (Schema::hasColumn('stock_purchases', 'is_historical')) {
            Schema::table('stock_purchases', function (Blueprint $table): void {
                $table->dropColumn(['is_historical', 'historical_imported_at']);
            });
        }
    }
};
