<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('counted_cash', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['opened_at', 'closed_at']);
        });

        Schema::create('cash_register_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('type', 40)->index();
            $table->string('direction', 10);
            $table->decimal('amount', 15, 2);
            $table->string('source_type', 80)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['cash_register_session_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->foreignId('cash_register_session_id')->nullable()->after('id')->constrained('cash_register_sessions')->nullOnDelete();
            $table->index(['cash_register_session_id', 'created_at']);
        });

        Schema::table('pos_sale_returns', function (Blueprint $table): void {
            $table->foreignId('cash_register_session_id')->nullable()->after('id')->constrained('cash_register_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sale_returns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cash_register_session_id');
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropIndex(['cash_register_session_id', 'created_at']);
            $table->dropConstrainedForeignId('cash_register_session_id');
        });

        Schema::dropIfExists('cash_register_movements');
        Schema::dropIfExists('cash_register_sessions');
    }
};
