<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 140)->unique();
                $table->string('type', 40)->default('other')->index();
                $table->string('account_name', 160)->nullable();
                $table->string('account_number', 160)->nullable();
                $table->text('instructions')->nullable();
                $table->boolean('is_cash')->default(false)->index();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        DB::table('payment_methods')->updateOrInsert(
            ['code' => 'cash'],
            [
                'name' => 'Cash',
                'type' => 'cash',
                'is_cash' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 0,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
            ],
        );

        $this->addPaymentMethodColumn('pos_sales', 'payment_method_id', 'pos_sales_payment_method_idx');
        $this->addPaymentMethodColumn('pos_held_sales', 'payment_method_id', 'pos_hold_payment_method_idx');
        $this->addPaymentMethodColumn('pos_sale_returns', 'payment_method_id', 'pos_return_payment_method_idx');
        $this->addPaymentMethodColumn('cash_register_movements', 'payment_method_id', 'cr_move_payment_method_idx');

        if (! Schema::hasTable('cash_register_session_balances')) {
            Schema::create('cash_register_session_balances', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('cash_register_session_id');
                $table->unsignedBigInteger('payment_method_id');
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->decimal('expected_closing_balance', 15, 2)->nullable();
                $table->decimal('counted_closing_balance', 15, 2)->nullable();
                $table->decimal('variance', 15, 2)->nullable();
                $table->timestamps();
                $table->unique(['cash_register_session_id', 'payment_method_id'], 'cr_balance_session_method_uq');
                $table->index('cash_register_session_id', 'cr_balance_session_idx');
                $table->index('payment_method_id', 'cr_balance_method_idx');
            });
        }

        $cashId = (int) DB::table('payment_methods')->where('code', 'cash')->value('id');

        DB::table('pos_sales')
            ->whereNull('payment_method_id')
            ->whereRaw('LOWER(payment_method) = ?', ['cash'])
            ->update(['payment_method_id' => $cashId]);

        DB::table('pos_held_sales')
            ->whereNull('payment_method_id')
            ->whereRaw('LOWER(payment_method) = ?', ['cash'])
            ->update(['payment_method_id' => $cashId]);

        DB::table('pos_sale_returns')
            ->whereNull('payment_method_id')
            ->whereRaw('LOWER(refund_method) = ?', ['cash'])
            ->update(['payment_method_id' => $cashId]);

        DB::table('cash_register_movements')
            ->whereNull('payment_method_id')
            ->update(['payment_method_id' => $cashId]);

        DB::table('cash_register_sessions')->orderBy('id')->chunkById(200, function ($sessions) use ($cashId): void {
            foreach ($sessions as $session) {
                DB::table('cash_register_session_balances')->updateOrInsert(
                    [
                        'cash_register_session_id' => $session->id,
                        'payment_method_id' => $cashId,
                    ],
                    [
                        'opening_balance' => $session->opening_cash ?? 0,
                        'expected_closing_balance' => $session->expected_cash,
                        'counted_closing_balance' => $session->counted_cash,
                        'variance' => $session->variance,
                        'created_at' => $session->created_at ?? now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }

    private function addPaymentMethodColumn(string $tableName, string $column, string $index): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $index): void {
            $table->unsignedBigInteger($column)->nullable();
            $table->index($column, $index);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_session_balances');

        foreach ([
            ['pos_sales', 'payment_method_id'],
            ['pos_held_sales', 'payment_method_id'],
            ['pos_sale_returns', 'payment_method_id'],
            ['cash_register_movements', 'payment_method_id'],
        ] as [$tableName, $column]) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        Schema::dropIfExists('payment_methods');
    }
};
