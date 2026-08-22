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
            throw new RuntimeException('Open a cash register before completing POS transactions.');
        }

        return $session;
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
    ): object {
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Invalid cash movement direction.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Cash movement amount must be greater than zero.');
        }

        $session = DB::table('cash_register_sessions')->where('id', $sessionId)->lockForUpdate()->first();
        if (! $session || $session->status !== 'open') {
            throw new RuntimeException('Cash register session is not open.');
        }

        $id = DB::table('cash_register_movements')->insertGetId([
            'cash_register_session_id' => $sessionId,
            'reference' => 'CSH-'.now()->format('ymd-His').'-'.strtoupper(Str::random(5)),
            'type' => $type,
            'direction' => $direction,
            'amount' => round($amount, 2),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reason' => $reason,
            'notes' => $notes,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('cash_register_movements')->find($id);
    }

    public function expectedCash(int $sessionId): float
    {
        $session = DB::table('cash_register_sessions')->where('id', $sessionId)->first();
        if (! $session) {
            throw new RuntimeException('Cash register session was not found.');
        }

        $cashIn = (float) DB::table('cash_register_movements')
            ->where('cash_register_session_id', $sessionId)
            ->where('direction', 'in')
            ->sum('amount');
        $cashOut = (float) DB::table('cash_register_movements')
            ->where('cash_register_session_id', $sessionId)
            ->where('direction', 'out')
            ->sum('amount');

        return round((float) $session->opening_cash + $cashIn - $cashOut, 2);
    }
}
