<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_units')) {
            Schema::create('product_units', function (Blueprint $table): void {
                $table->id();
                // Keep legacy-compatible indexed IDs instead of physical FKs.
                $table->unsignedBigInteger('product_id')->index('product_units_product_idx');
                $table->unsignedInteger('unit_number');
                $table->string('barcode', 80)->unique();
                $table->string('status', 24)->default('available')->index();
                $table->string('source_type', 80)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->decimal('unit_cost', 15, 4)->nullable();
                $table->unsignedBigInteger('pos_sale_id')->nullable()->index('product_units_sale_idx');
                $table->unsignedBigInteger('pos_sale_item_id')->nullable()->index('product_units_sale_item_idx');
                $table->timestamp('sold_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamp('removed_at')->nullable();
                $table->timestamp('last_printed_at')->nullable();
                $table->unsignedInteger('print_count')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'unit_number'], 'product_units_product_number_uq');
                $table->index(['product_id', 'status'], 'product_units_product_status_idx');
            });
        }

        // Establish one persistent physical-unit record for every currently
        // available tracked stock item. Existing product IDs are intentionally
        // not constrained because older production schemas use legacy key types.
        DB::table('products')
            ->where('track_stock', true)
            ->where('stock_quantity', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    $existing = (int) DB::table('product_units')
                        ->where('product_id', $product->id)
                        ->where('status', 'available')
                        ->count();
                    $missing = max(0, (int) $product->stock_quantity - $existing);
                    $next = (int) DB::table('product_units')->where('product_id', $product->id)->max('unit_number') + 1;

                    for ($i = 0; $i < $missing; $i++) {
                        $number = $next + $i;
                        DB::table('product_units')->insert([
                            'product_id' => $product->id,
                            'unit_number' => $number,
                            'barcode' => 'PX-'.$product->id.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                            'status' => 'available',
                            'source_type' => 'legacy_stock',
                            'source_id' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
