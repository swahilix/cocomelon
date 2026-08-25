<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('customer_name', 160);
            $table->string('customer_phone', 30)->index();
            $table->decimal('subtotal', 15, 2);
            $table->string('discount_type', 20)->default('none');
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->unsignedBigInteger('released_by')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('installment_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('product_name', 190);
            $table->string('sku', 190)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->unsignedBigInteger('reservation_movement_id')->nullable()->index();
            $table->unsignedBigInteger('return_movement_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('installment_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->string('reference', 50)->unique();
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('payment_method_id')->nullable()->index();
            $table->string('payment_method_name', 120);
            $table->string('external_reference', 190)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->string('sms_status', 20)->default('pending')->index();
            $table->text('sms_error')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installment_plan_items');
        Schema::dropIfExists('installment_plans');
    }
};
