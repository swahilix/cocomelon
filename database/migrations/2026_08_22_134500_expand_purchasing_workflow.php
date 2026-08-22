<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->string('status', 30)->default('received')->after('purchase_date')->index();
            $table->date('expected_date')->nullable()->after('status')->index();
            $table->date('received_date')->nullable()->after('expected_date')->index();
            $table->decimal('discount', 15, 2)->default(0)->after('subtotal');
            $table->decimal('tax', 15, 2)->default(0)->after('discount');
            $table->decimal('amount_paid', 15, 2)->default(0)->after('total');
            $table->decimal('balance_due', 15, 2)->default(0)->after('amount_paid');
            $table->string('payment_status', 20)->default('unpaid')->after('balance_due')->index();
            $table->timestamp('cancelled_at')->nullable()->after('notes');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at')->index();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
        });

        Schema::table('stock_purchase_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity_received')->default(0)->after('quantity');
            $table->unsignedInteger('quantity_returned')->default(0)->after('quantity_received');
            $table->decimal('discount', 15, 2)->default(0)->after('unit_cost');
            $table->decimal('tax', 15, 2)->default(0)->after('discount');
        });

        Schema::create('stock_purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_purchase_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 50)->unique();
            $table->date('received_date')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('stock_purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_purchase_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->timestamps();
        });

        Schema::create('stock_purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('reference', 50)->unique();
            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 40);
            $table->string('transaction_reference', 120)->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('stock_purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('reference', 50)->unique();
            $table->date('return_date')->index();
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->string('reason', 120);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('stock_purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_purchase_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_credit', 15, 2)->default(0);
            $table->timestamps();
        });

        DB::table('stock_purchase_items')->update([
            'quantity_received' => DB::raw('quantity'),
        ]);

        DB::table('stock_purchases')->orderBy('id')->get()->each(function ($purchase): void {
            $paid = $purchase->payment_method === 'credit' ? 0 : (float) $purchase->total;
            DB::table('stock_purchases')->where('id', $purchase->id)->update([
                'status' => 'received',
                'received_date' => $purchase->purchase_date,
                'amount_paid' => $paid,
                'balance_due' => max(0, (float) $purchase->total - $paid),
                'payment_status' => $paid >= (float) $purchase->total ? 'paid' : 'unpaid',
            ]);

            if ($paid > 0) {
                DB::table('stock_purchase_payments')->insert([
                    'stock_purchase_id' => $purchase->id,
                    'supplier_id' => $purchase->supplier_id,
                    'reference' => 'LEGACY-PAY-'.$purchase->id,
                    'payment_date' => $purchase->purchase_date,
                    'amount' => $paid,
                    'payment_method' => $purchase->payment_method,
                    'transaction_reference' => null,
                    'notes' => 'Migrated from legacy purchase payment state.',
                    'created_by' => $purchase->created_by,
                    'created_at' => $purchase->created_at,
                    'updated_at' => $purchase->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_purchase_return_items');
        Schema::dropIfExists('stock_purchase_returns');
        Schema::dropIfExists('stock_purchase_payments');
        Schema::dropIfExists('stock_purchase_receipt_items');
        Schema::dropIfExists('stock_purchase_receipts');

        Schema::table('stock_purchase_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_received', 'quantity_returned', 'discount', 'tax']);
        });

        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'expected_date', 'received_date', 'discount', 'tax', 'amount_paid',
                'balance_due', 'payment_status', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
            ]);
        });
    }
};