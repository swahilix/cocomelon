<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\CashRegisterService;
use App\Services\PostingGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class RegisterController extends Controller
{
    public function index(Request $request, CashRegisterService $registers): Response
    {
        $userId = $request->user()?->id;
        $current = $registers->currentOpenSession($userId);

        $sessions = DB::table('cash_register_sessions')
            ->leftJoin('users', 'users.id', '=', 'cash_register_sessions.user_id')
            ->leftJoin('users as closers', 'closers.id', '=', 'cash_register_sessions.closed_by')
            ->select('cash_register_sessions.*', 'users.name as cashier_name', 'closers.name as closed_by_name')
            ->latest('cash_register_sessions.id')
            ->limit(100)
            ->get();

        return Inertia::render('Admin/Registers/Index', [
            'current' => $current ? $this->sessionDetail($current->id, $registers) : null,
            'sessions' => $sessions,
            'summary' => [
                'open_sessions' => DB::table('cash_register_sessions')->where('status', 'open')->count(),
                'today_closed' => DB::table('cash_register_sessions')->where('status', 'closed')->whereDate('closed_at', now()->toDateString())->count(),
                'today_variance' => (float) DB::table('cash_register_sessions')->whereDate('closed_at', now()->toDateString())->sum('variance'),
            ],
        ]);
    }

    public function show(int $session, CashRegisterService $registers): Response
    {
        return Inertia::render('Admin/Registers/Show', [
            'register' => $this->sessionDetail($session, $registers),
        ]);
    }

    public function open(Request $request, PostingGuard $guard, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:1000'],
            '_operation_id' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $guard, $audit): void {
                $userId = $request->user()?->id;
                DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                $exists = DB::table('cash_register_sessions')->where('user_id', $userId)->where('status', 'open')->exists();
                if ($exists) throw new RuntimeException('You already have an open register session.');

                $guard->claim($request, 'register.open', 'cash_register_session');
                $reference = 'REG-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
                $sessionId = DB::table('cash_register_sessions')->insertGetId([
                    'reference' => $reference,
                    'user_id' => $userId,
                    'opening_cash' => $validated['opening_cash'],
                    'opened_at' => now(),
                    'status' => 'open',
                    'opening_notes' => $validated['opening_notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $after = DB::table('cash_register_sessions')->where('id', $sessionId)->first();
                $audit->record($request, 'register.opened', 'cash_register_session', $sessionId, $reference, null, $after);
            });
        } catch (Throwable $e) {
            return back()->withErrors(['register' => $e->getMessage()]);
        }

        return back()->with('success', 'Cash register opened successfully.');
    }

    public function movement(Request $request, CashRegisterService $registers, PostingGuard $guard, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:in,out,expense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            '_operation_id' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $registers, $guard, $audit): void {
                $session = $registers->requireOpenSession($request->user()?->id);
                $guard->claim($request, 'register.movement', 'cash_register_session', $session->id);
                $type = match ($validated['direction']) {
                    'in' => 'cash_in',
                    'expense' => 'expense',
                    default => 'cash_out',
                };
                $direction = $validated['direction'] === 'in' ? 'in' : 'out';

                $movementId = $registers->record(
                    $session->id,
                    $type,
                    $direction,
                    (float) $validated['amount'],
                    $validated['reason'],
                    $validated['notes'] ?? null,
                    $request->user()?->id,
                    $type === 'expense' ? 'expense_cash_payment' : 'manual_cash_movement',
                    null,
                );

                $after = DB::table('cash_register_movements')->where('id', $movementId)->first();
                $audit->record($request, 'register.cash_movement', 'cash_register_movement', $movementId, $after?->reference, null, $after, ['session_id' => $session->id]);
            });
        } catch (Throwable $e) {
            return back()->withErrors(['movement' => $e->getMessage()]);
        }

        return back()->with('success', 'Cash movement recorded.');
    }

    public function close(Request $request, CashRegisterService $registers, PostingGuard $guard, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:1500'],
            '_operation_id' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $registers, $guard, $audit): void {
                $session = $registers->requireOpenSession($request->user()?->id);
                $before = DB::table('cash_register_sessions')->where('id', $session->id)->lockForUpdate()->first();
                if (! $before || $before->status !== 'open') throw new RuntimeException('Register is already closed or unavailable.');

                $guard->claim($request, 'register.close', 'cash_register_session', $session->id);
                $expected = $registers->expectedCash($session->id);
                $counted = round((float) $validated['counted_cash'], 2);

                DB::table('cash_register_sessions')->where('id', $session->id)->update([
                    'expected_cash' => $expected,
                    'counted_cash' => $counted,
                    'variance' => round($counted - $expected, 2),
                    'closed_at' => now(),
                    'closed_by' => $request->user()?->id,
                    'status' => 'closed',
                    'closing_notes' => $validated['closing_notes'] ?? null,
                    'updated_at' => now(),
                ]);

                $after = DB::table('cash_register_sessions')->where('id', $session->id)->first();
                $audit->record($request, 'register.closed', 'cash_register_session', $session->id, $before->reference, $before, $after);
            });
        } catch (Throwable $e) {
            return back()->withErrors(['close' => $e->getMessage()]);
        }

        return back()->with('success', 'Register closed and cash variance recorded.');
    }

    private function sessionDetail(int $sessionId, CashRegisterService $registers): array
    {
        $session = DB::table('cash_register_sessions')
            ->leftJoin('users', 'users.id', '=', 'cash_register_sessions.user_id')
            ->leftJoin('users as closers', 'closers.id', '=', 'cash_register_sessions.closed_by')
            ->where('cash_register_sessions.id', $sessionId)
            ->select('cash_register_sessions.*', 'users.name as cashier_name', 'closers.name as closed_by_name')
            ->first();
        abort_unless($session, 404);

        $movements = DB::table('cash_register_movements')
            ->leftJoin('users', 'users.id', '=', 'cash_register_movements.created_by')
            ->where('cash_register_session_id', $sessionId)
            ->select('cash_register_movements.*', 'users.name as created_by_name')
            ->orderBy('cash_register_movements.id')
            ->get();

        $sales = DB::table('pos_sales')->where('cash_register_session_id', $sessionId)->get();
        $returns = DB::table('pos_sale_returns')->where('cash_register_session_id', $sessionId)->get();

        $cashSales = (float) $movements->where('type', 'cash_sale')->where('direction', 'in')->sum('amount');
        $nonCashSales = (float) $sales->where('payment_method', '!=', 'cash')->sum('total');
        $cashRefunds = (float) $movements->filter(fn ($m) => in_array($m->type, ['cash_refund', 'cash_void_refund'], true) && $m->direction === 'out')->sum('amount');
        $cashIn = (float) $movements->where('direction', 'in')->where('type', 'cash_in')->sum('amount');
        $cashOut = (float) $movements->where('direction', 'out')->where('type', 'cash_out')->sum('amount');
        $expenseCashOut = (float) $movements->where('direction', 'out')->where('type', 'expense')->sum('amount');
        $expected = $session->status === 'open' ? $registers->expectedCash($sessionId) : (float) $session->expected_cash;

        return [
            'session' => $session,
            'movements' => $movements,
            'sales' => $sales,
            'returns' => $returns,
            'summary' => [
                'cash_sales' => $cashSales,
                'non_cash_sales' => $nonCashSales,
                'cash_refunds' => $cashRefunds,
                'manual_cash_in' => $cashIn,
                'manual_cash_out' => $cashOut,
                'expense_cash_out' => $expenseCashOut,
                'expected_cash' => $expected,
                'counted_cash' => $session->counted_cash !== null ? (float) $session->counted_cash : null,
                'variance' => $session->variance !== null ? (float) $session->variance : null,
            ],
        ];
    }
}
