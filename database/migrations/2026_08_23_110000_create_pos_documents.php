<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_documents')) {
            Schema::create('pos_documents', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 20);
                $table->string('reference', 80)->unique();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone', 60)->nullable();
                $table->string('customer_email')->nullable();
                $table->date('document_date');
                $table->date('valid_until')->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->string('status', 30)->default('issued');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['type', 'document_date'], 'pos_docs_type_date_idx');
                $table->index('customer_id', 'pos_docs_customer_idx');
                $table->index('created_by', 'pos_docs_creator_idx');
            });
        }

        if (! Schema::hasTable('pos_document_items')) {
            Schema::create('pos_document_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pos_document_id')->constrained('pos_documents')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_name');
                $table->string('sku', 120)->nullable();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();

                $table->index('product_id', 'pos_doc_items_product_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_document_items');
        Schema::dropIfExists('pos_documents');
    }
};
