<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->string('status', 30)->default('completed')->after('payment_reference')->index();
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('status');
            $table->timestamp('voided_at')->nullable()->after('refunded_amount');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable()->after('voided_by');
        });

        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity_returned')->default(0)->after('quantity');
        });

        Schema::create('pos_sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->restrictOnDelete();
            $table->date('return_date')->index();
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->string('refund_method', 40)->default('cash');
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pos_sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_return_id')->constrained('pos_sale_returns')->cascadeOnDelete();
            $table->foreignId('pos_sale_item_id')->constrained('pos_sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('refund_amount', 15, 2);
            $table->boolean('restock')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_return_items');
        Schema::dropIfExists('pos_sale_returns');

        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->dropColumn('quantity_returned');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['status', 'refunded_amount', 'voided_at', 'voided_by', 'void_reason']);
        });
    }
};
