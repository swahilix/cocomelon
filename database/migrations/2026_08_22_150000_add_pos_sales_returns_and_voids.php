<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('pos_sales', 'status')) {
            Schema::table('pos_sales', fn (Blueprint $table) => $table->string('status', 30)->default('completed')->index());
        }
        if (! Schema::hasColumn('pos_sales', 'refunded_amount')) {
            Schema::table('pos_sales', fn (Blueprint $table) => $table->decimal('refunded_amount', 15, 2)->default(0)->after('status'));
        }
        if (! Schema::hasColumn('pos_sales', 'voided_at')) {
            Schema::table('pos_sales', fn (Blueprint $table) => $table->timestamp('voided_at')->nullable()->after('refunded_amount'));
        }
        if (! Schema::hasColumn('pos_sales', 'voided_by')) {
            Schema::table('pos_sales', fn (Blueprint $table) => $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at')->index());
        }
        if (! Schema::hasColumn('pos_sales', 'void_reason')) {
            Schema::table('pos_sales', fn (Blueprint $table) => $table->string('void_reason', 500)->nullable()->after('voided_by'));
        }

        if (! Schema::hasColumn('pos_sale_items', 'quantity_returned')) {
            Schema::table('pos_sale_items', fn (Blueprint $table) => $table->unsignedInteger('quantity_returned')->default(0)->after('quantity'));
        }

        if (! Schema::hasTable('pos_sale_returns')) {
            Schema::create('pos_sale_returns', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 50)->unique();
                $table->foreignId('pos_sale_id')->constrained('pos_sales')->restrictOnDelete();
                $table->date('return_date')->index();
                $table->decimal('refund_amount', 15, 2)->default(0);
                $table->string('refund_method', 40)->default('cash');
                $table->string('reason', 255);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_sale_return_items')) {
            Schema::create('pos_sale_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_sale_return_id')->constrained('pos_sale_returns')->cascadeOnDelete();
                $table->foreignId('pos_sale_item_id')->constrained('pos_sale_items')->restrictOnDelete();
                // Production has a legacy physical type for products.id. Keep the
                // relationship identifier and index without a database FK.
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('refund_amount', 15, 2);
                $table->boolean('restock')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_return_items');
        Schema::dropIfExists('pos_sale_returns');

        if (Schema::hasColumn('pos_sale_items', 'quantity_returned')) {
            Schema::table('pos_sale_items', fn (Blueprint $table) => $table->dropColumn('quantity_returned'));
        }

        $columns = array_values(array_filter(
            ['status', 'refunded_amount', 'voided_at', 'voided_by', 'void_reason'],
            fn (string $column): bool => Schema::hasColumn('pos_sales', $column),
        ));
        if ($columns !== []) {
            Schema::table('pos_sales', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
