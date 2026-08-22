<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'low_stock_threshold')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock_quantity');
            });
        }

        if (! Schema::hasColumn('stock_movements', 'balance_before')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->unsignedInteger('balance_before')->nullable()->after('quantity');
            });
        }

        if (! Schema::hasColumn('stock_movements', 'source_type')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->string('source_type', 50)->nullable()->after('balance_after')->index();
            });
        }

        if (! Schema::hasColumn('stock_movements', 'source_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type')->index();
            });
        }

        if (! Schema::hasColumn('stock_movements', 'reason')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->string('reason', 80)->nullable()->after('reference')->index();
            });
        }

        if (! Schema::hasColumn('stock_movements', 'reversal_of_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->foreignId('reversal_of_id')->nullable()->after('reason')->constrained('stock_movements')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_movements', 'reversal_of_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropForeign(['reversal_of_id']);
                $table->dropColumn('reversal_of_id');
            });
        }

        foreach (['reason', 'source_id', 'source_type', 'balance_before'] as $column) {
            if (Schema::hasColumn('stock_movements', $column)) {
                Schema::table('stock_movements', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn('products', 'low_stock_threshold')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('low_stock_threshold');
            });
        }
    }
};
