<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock_quantity');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unsignedInteger('balance_before')->nullable()->after('quantity');
            $table->string('source_type', 50)->nullable()->after('balance_after')->index();
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type')->index();
            $table->string('reason', 80)->nullable()->after('reference')->index();
            $table->foreignId('reversal_of_id')->nullable()->after('reason')->constrained('stock_movements')->nullOnDelete();
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['reversal_of_id']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['balance_before', 'source_type', 'source_id', 'reason', 'reversal_of_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
