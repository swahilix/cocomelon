<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CashRegisterService
{
    public function currentOpenSession(?int $userId): ?object
    {
        if (! $userId) {
            return null;
        }

        return DB::table('cash_register_sessions')
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    public function requireOpenSession(?int $userId): object
    {
        $session = $this->currentOpenSession($userId);

        if (! $session) {
            throw new RuntimeException('Open a register before completing POS transactions.');
        }

        return $session;
    }

    public function cashMethod(): object
    {
        $method = DB::table('payment_methods')->where('is_cash', true)->where('is_system', true)->first();

        if (! $method) {
            throw new RuntimeException('The built-in Cash payment method is unavailable.');
        }

        return $method;
    }

    public function methodsForSession(int $sessionId): array
    {
        return DB::table('cash_register_session_balances')
            ->join('payment_methods', 'payment_methods.id', '=', 'cash_register_session_balances.payment_method_id')
            ->where('cash_register_session_balances.cash_register_session_id', $sessionId)
            ->orderBy('payment_methods.sort_order')
            ->orderBy('payment_methods.name')
            ->get([
                'payment_methods.id',
                'payment_methods.name',
                'payment_methods.code',
                'payment_methods.type',
                'payment_methods.account_name',
                'payment_methods.account_number',
                'payment_methods.instructions',
                'payment_methods.is_cash',
                'payment_methods.is_system',
                'payment_methods.is_active',
            ])
            ->all();
    }

    public function methodForSession(int $sessionId, int $paymentMethodId): object
    {
        $method = DB::table('cash_register_session_balances')
            ->join('payment_methods', 'payment_methods.id', '=', 'cash_register_session_balances.payment_method_id')
            ->where('cash_register_session_balances.cash_register_session_id', $sessionId)
            ->where('cash_register_session_balances.payment_method_id', $paymentMethodId)
            ->select('payment_methods.*')
            ->first();

        if (! $method) {
            throw new RuntimeException('This payment method was not included when the register was opened. Close the shift and open a new one to use it.');
        }

        return $method;
    }

    public function record(
        int $sessionId,
        string $type,
        string $direction,
        float $amount,
        ?string $reason = null,
        ?string $notes = null,
        ?int $userId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $paymentMethodId = null,
    ): int {
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Invalid register movement direction.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Register movement amount must be greater than zero.');
        }

        $session = DB::table('cash_register_sessions')->where('id', $sessionId)->lockForUpdate()->first();
        if (! $session || $session->status !== 'open') {
            throw new RuntimeException('Register session is not open.');
        }

        $paymentMethodId ??= (int) $this->cashMethod()->id;
        $method = $this->methodForSession($sessionId, $paymentMethodId);

        return DB::table('cash_register_movements')->insertGetId([
            'cash_register_session_id' => $sessionId,
            'payment_method_id' => $paymentMethodId,
            'reference' => 'REG-'.now()->format('ymd-His').'-'.strtoupper(Str::random(5)),
            'type' => $type,
            'direction' => $direction,
            'amount' => round($amount, 2),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reason' => $reason ?: $method->name,
            'notes' => $notes,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function expectedBalance(int $sessionId, int $paymentMethodId): float
    {
        $balance = DB::table('cash_register_session_balances')
            ->where('cash_register_session_id', $sessionId)
            ->where('payment_method_id', $paymentMethodId)
            ->first();

        if (! $balance) {
            throw new RuntimeException('Register opening balance was not found for this payment method.');
        }

        $method = DB::table('payment_methods')->where('id', $paymentMethodId)->first();
        $isCash = (bool) ($method?->is_cash ?? false);

        $movements = DB::table('cash_register_movements')
            ->where('cash_register_session_id', $sessionId)
            ->where(function ($query) use ($paymentMethodId, $isCash): void {
                $query->where('payment_method_id', $paymentMethodId);
                if ($isCash) {
                    $query->orWhereNull('payment_method_id');
                }
            });

        $in = (float) (clone $movements)->where('direction', 'in')->sum('amount');
        $out = (float) (clone $movements)->where('direction', 'out')->sum('amount');

        return round((float) $balance->opening_balance + $in - $out, 2);
    }

    public function expectedCash(int $sessionId): float
    {
        return $this->expectedBalance($sessionId, (int) $this->cashMethod()->id);
    }

    public function sessionBalances(int $sessionId): array
    {
        return DB::table('cash_register_session_balances')
            ->join('payment_methods', 'payment_methods.id', '=', 'cash_register_session_balances.payment_method_id')
            ->where('cash_register_session_balances.cash_register_session_id', $sessionId)
            ->orderBy('payment_methods.sort_order')
            ->orderBy('payment_methods.name')
            ->get([
                'cash_register_session_balances.*',
                'payment_methods.name as payment_method_name',
                'payment_methods.code as payment_method_code',
                'payment_methods.type as payment_method_type',
                'payment_methods.account_name',
                'payment_methods.account_number',
                'payment_methods.is_cash',
                'payment_methods.is_active',
            ])
            ->map(function ($row) use ($sessionId): object {
                if ($row->expected_closing_balance === null) {
                    $row->expected_closing_balance = $this->expectedBalance($sessionId, (int) $row->payment_method_id);
                }
                return $row;
            })
            ->all();
    }
}
