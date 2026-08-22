<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CashRegisterService;
use Carbon\Carbon;
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

        $currentDetail = $current ? $this->sessionDetail($current->id, $registers) : null;

        return Inertia::render('Admin/Registers/Index', [
            'current' => $currentDetail,
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

    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request): void {
                $userId = $request->user()?->id;
                $exists = DB::table('cash_register_sessions')->where('user_id', $userId)->where('status', 'open')->lockForUpdate()->exists();
                if ($exists) {
                    throw new RuntimeException('You already have an open register session.');
                }

                DB::table('cash_register_sessions')->insert([
                    'reference' => 'REG-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4)),
                    'user_id' => $userId,
                    'opening_cash' => $validated['opening_cash'],
                    'opened_at' => now(),
                    'status' => 'open',
                    'opening_notes' => $validated['opening_notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            return back()->withErrors(['register' => $e->getMessage()]);
        }

        return back()->with('success', 'Cash register opened successfully.');
    }

    public function movement(Request $request, CashRegisterService $registers): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $registers): void {
                $session = $registers->requireOpenSession($request->user()?->id);
                $registers->record(
                    $session->id,
                    $validated['direction'] === 'in' ? 'cash_in' : 'cash_out',
                    $validated['direction'],
                    (float) $validated['amount'],
                    $validated['reason'],
                    $validated['notes'] ?? null,
                    $request->user()?->id,
                    'manual_cash_movement',
                    null,
                );
            });
        } catch (Throwable $e) {
            return back()->withErrors(['movement' => $e->getMessage()]);
        }

        return back()->with('success', 'Cash movement recorded.');
    }

    public function close(Request $request, CashRegisterService $registers): RedirectResponse
    {
        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:1500'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $registers): void {
                $session = $registers->requireOpenSession($request->user()?->id);
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

        $sales = DB::table('pos_sales')
            ->where('cash_register_session_id', $sessionId)
            ->where('status', '!=', 'voided')
            ->get();
        $returns = DB::table('pos_sale_returns')->where('cash_register_session_id', $sessionId)->get();

        $cashSales = (float) $sales->where('payment_method', 'cash')->sum('total');
        $nonCashSales = (float) $sales->where('payment_method', '!=', 'cash')->sum('total');
        $cashRefunds = (float) $returns->where('refund_method', 'cash')->sum('refund_amount');
        $cashIn = (float) $movements->where('direction', 'in')->where('type', 'cash_in')->sum('amount');
        $cashOut = (float) $movements->where('direction', 'out')->where('type', 'cash_out')->sum('amount');
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
                'expected_cash' => $expected,
                'counted_cash' => $session->counted_cash !== null ? (float) $session->counted_cash : null,
                'variance' => $session->variance !== null ? (float) $session->variance : null,
            ],
        ];
    }
}
