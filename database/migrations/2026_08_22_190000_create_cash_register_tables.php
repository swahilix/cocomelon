<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_register_sessions')) {
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

                $table->index(['user_id', 'status'], 'cr_session_user_status_idx');
                $table->index(['opened_at', 'closed_at'], 'cr_session_open_close_idx');
            });
        }

        if (! Schema::hasTable('cash_register_movements')) {
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

                $table->index(['cash_register_session_id', 'created_at'], 'cr_mov_session_created_idx');
                $table->index(['source_type', 'source_id'], 'cr_mov_source_idx');
            });
        } else {
            // A previous MySQL attempt can leave the table behind after failing
            // while adding its auto-generated over-length composite index.
            $indexes = collect(Schema::getIndexes('cash_register_movements'));
            $hasSessionCreatedIndex = $indexes->contains(function (array $index): bool {
                $columns = array_values($index['columns'] ?? []);
                return $columns === ['cash_register_session_id', 'created_at'];
            });
            $hasSourceIndex = $indexes->contains(function (array $index): bool {
                $columns = array_values($index['columns'] ?? []);
                return $columns === ['source_type', 'source_id'];
            });

            Schema::table('cash_register_movements', function (Blueprint $table) use ($hasSessionCreatedIndex, $hasSourceIndex): void {
                if (! $hasSessionCreatedIndex) {
                    $table->index(['cash_register_session_id', 'created_at'], 'cr_mov_session_created_idx');
                }
                if (! $hasSourceIndex) {
                    $table->index(['source_type', 'source_id'], 'cr_mov_source_idx');
                }
            });
        }

        if (! Schema::hasColumn('pos_sales', 'cash_register_session_id')) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->foreignId('cash_register_session_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('cash_register_sessions')
                    ->nullOnDelete();
            });
        }

        $posSalesIndexes = collect(Schema::getIndexes('pos_sales'));
        $hasPosSessionCreatedIndex = $posSalesIndexes->contains(function (array $index): bool {
            $columns = array_values($index['columns'] ?? []);
            return $columns === ['cash_register_session_id', 'created_at'];
        });
        if (! $hasPosSessionCreatedIndex) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->index(['cash_register_session_id', 'created_at'], 'pos_register_created_idx');
            });
        }

        if (! Schema::hasColumn('pos_sale_returns', 'cash_register_session_id')) {
            Schema::table('pos_sale_returns', function (Blueprint $table): void {
                $table->foreignId('cash_register_session_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('cash_register_sessions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_sale_returns', 'cash_register_session_id')) {
            Schema::table('pos_sale_returns', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('cash_register_session_id');
            });
        }

        if (Schema::hasColumn('pos_sales', 'cash_register_session_id')) {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->dropIndex('pos_register_created_idx');
                $table->dropConstrainedForeignId('cash_register_session_id');
            });
        }

        Schema::dropIfExists('cash_register_movements');
        Schema::dropIfExists('cash_register_sessions');
    }
};
