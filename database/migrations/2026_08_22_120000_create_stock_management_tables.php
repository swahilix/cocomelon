<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('stock_purchases')) {
            Schema::create('stock_purchases', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->string('supplier_name')->nullable();
                $table->string('invoice_number')->nullable()->index();
                $table->date('purchase_date')->index();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('additional_cost', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->string('payment_method', 40)->default('cash');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_purchase_items')) {
            Schema::create('stock_purchase_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_purchase_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_cost', 15, 2);
                $table->decimal('line_total', 15, 2);
                $table->timestamps();
                $table->index(['product_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('pos_sales')) {
            Schema::create('pos_sales', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone')->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->decimal('amount_paid', 15, 2)->default(0);
                $table->decimal('change_due', 15, 2)->default(0);
                $table->string('payment_method', 40)->default('cash');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('pos_sale_items')) {
            Schema::create('pos_sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->string('product_name');
                $table->string('sku', 191)->nullable();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2);
                $table->timestamps();
                $table->index(['product_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->date('expense_date')->index();
                $table->string('category', 80)->index();
                $table->string('description');
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 40)->default('cash');
                $table->string('payee')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->enum('direction', ['in', 'out', 'adjustment'])->index();
                $table->string('type', 40)->index();
                $table->integer('quantity');
                $table->unsignedInteger('balance_after')->nullable();
                $table->decimal('unit_cost', 15, 2)->nullable();
                $table->string('reference', 80)->nullable()->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['product_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('stock_purchase_items');
        Schema::dropIfExists('stock_purchases');
    }
};
