<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranties', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_name', 190);
            $table->string('sku', 190)->nullable();
            $table->string('serial_number', 190)->nullable()->index();
            $table->unsignedBigInteger('pos_customer_id')->nullable()->index();
            $table->string('customer_name', 160);
            $table->string('customer_phone', 30)->index();
            $table->unsignedSmallInteger('duration_value');
            $table->string('duration_unit', 10);
            $table->date('starts_on');
            $table->date('ends_on')->index();
            $table->string('sms_language', 5)->default('en');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('expiry_notified_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('warranty_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warranty_id')->constrained('warranties')->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('phone', 30);
            $table->text('message');
            $table->string('status', 20)->default('pending')->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_messages');
        Schema::dropIfExists('warranties');
    }
};
