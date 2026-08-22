<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 60)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_held_sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 60)->nullable();
            $table->json('cart');
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('payment_method', 40)->default('cash');
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('reference')->constrained('pos_customers')->nullOnDelete();
            $table->string('payment_reference')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'payment_reference']);
        });

        Schema::dropIfExists('pos_held_sales');
        Schema::dropIfExists('pos_customers');
    }
};
