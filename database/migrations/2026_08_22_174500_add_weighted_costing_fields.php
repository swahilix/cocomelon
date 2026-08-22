<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('average_cost', 15, 4)->default(0)->after('stock_quantity');
            $table->decimal('last_purchase_cost', 15, 4)->default(0)->after('average_cost');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 15, 4)->nullable()->after('unit_price');
        });

        // Existing inventory predates weighted-average tracking. Seed a conservative
        // baseline from the latest received purchase cost without rewriting history.
        $products = DB::table('products')->select('id')->orderBy('id')->get();
        foreach ($products as $product) {
            $latestCost = DB::table('stock_purchase_receipt_items')
                ->where('product_id', $product->id)
                ->latest('id')
                ->value('unit_cost');

            if ($latestCost === null) {
                $latestCost = DB::table('stock_purchase_items')
                    ->where('product_id', $product->id)
                    ->latest('id')
                    ->value('unit_cost');
            }

            if ($latestCost !== null) {
                DB::table('products')->where('id', $product->id)->update([
                    'average_cost' => $latestCost,
                    'last_purchase_cost' => $latestCost,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['average_cost', 'last_purchase_cost']);
        });
    }
};
