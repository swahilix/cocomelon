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

        $paymentMethods = DB::table('payment_methods')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $todayVariance = (float) DB::table('cash_register_session_balances')
            ->join('cash_register_sessions', 'cash_register_sessions.id', '=', 'cash_register_session_balances.cash_register_session_id')
            ->whereDate('cash_register_sessions.closed_at', now()->toDateString())
            ->sum('cash_register_session_balances.variance');

        return Inertia::render('Admin/Registers/Index', [
            'current' => $current ? $this->sessionDetail($current->id, $registers) : null,
            'sessions' => $sessions,
            'paymentMethods' => $paymentMethods,
            'activePaymentMethods' => $paymentMethods->filter(fn ($method) => $method->is_active || $method->is_cash)->values(),
            'summary' => [
                'open_sessions' => DB::table('cash_register_sessions')->where('status', 'open')->count(),
                'today_closed' => DB::table('cash_register_sessions')->where('status', 'closed')->whereDate('closed_at', now()->toDateString())->count(),
                'today_variance' => $todayVariance,
            ],
        ]);
    }

    public function show(int $session, CashRegisterService $registers): Response
    {
        return Inertia::render('Admin/Registers/Show', [
            'register' => $this->sessionDetail($session, $registers),
        ]);
    }

    public function open(Request $request, PostingGuard $guard, AuditService $audit, CashRegisterService $registers): RedirectResponse
    {
        $validated = $request->validate([
            'opening_balances' => ['required', 'array'],
            'opening_balances.*' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:1000'],
            '_operation_id' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $guard, $audit, $registers): void {
                $userId = $request->user()?->id;
                DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                $exists = DB::table('cash_register_sessions')->where('user_id', $userId)->where('status', 'open')->exists();
                if ($exists) {
                    throw new RuntimeException('You already have an open register session.');
                }

                $methods = DB::table('payment_methods')
                    ->where(fn ($query) => $query->where('is_active', true)->orWhere('is_cash', true))
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                if ($methods->isEmpty()) {
                    throw new RuntimeException('No payment methods are available for this register.');
                }

                $cashMethod = $registers->cashMethod();
                $cashOpening = round((float) ($validated['opening_balances'][$cashMethod->id] ?? 0), 2);

                $guard->claim($request, 'register.open', 'cash_register_session');
                $reference = 'REG-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
                $sessionId = DB::table('cash_register_sessions')->insertGetId([
                    'reference' => $reference,
                    'user_id' => $userId,
                    'opening_cash' => $cashOpening,
                    'opened_at' => now(),
                    'status' => 'open',
                    'opening_notes' => $validated['opening_notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $openingSnapshot = [];
                foreach ($methods as $method) {
                    $opening = round((float) ($validated['opening_balances'][$method->id] ?? 0), 2);
                    DB::table('cash_register_session_balances')->insert([
                        'cash_register_session_id' => $sessionId,
                        'payment_method_id' => $method->id,
                        'opening_balance' => $opening,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $openingSnapshot[$method->name] = $opening;
                }

                $after = DB::table('cash_register_sessions')->where('id', $sessionId)->first();
                $audit->record($request, 'register.opened', 'cash_register_session', $sessionId, $reference, null, $after, [
                    'opening_balances' => $openingSnapshot,
                ]);
            });
        } catch (Throwable $e) {
            return back()->withErrors(['register' => $e->getMessage()]);
        }

        return back()->with('success', 'Register opened with payment-method opening balances.');
    }

    public function movement(Request $request, CashRegisterService $registers, PostingGuard $guard, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'direction' => ['required', 'in:in,out,expense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            '_operation_id' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $registers, $guard, $audit): void {
                $session = $registers->requireOpenSession($request->user()?->id);
                $method = $registers->methodForSession($session->id, (int) $validated['payment_method_id']);
                $guard->claim($request, 'register.movement', 'cash_register_session', $session->id);

                $direction = $validated['direction'] === 'in' ? 'in' : 'out';
                $type = match ($validated['direction']) {
                    'expense' => 'expense',
                    'in' => $method->is_cash ? 'cash_in' : 'payment_in',
                    default => $method->is_cash ? 'cash_out' : 'payment_out',
                };

                $movementId = $registers->record(
                    $session->id,
                    $type,
                    $direction,
                    (float) $validated['amount'],
                    $validated['reason'],
                    $validated['notes'] ?? null,
                    $request->user()?->id,
                    $type === 'expense' ? 'register_expense_payment' : 'manual_register_movement',
                    null,
                    (int) $method->id,
                );

                $after = DB::table('cash_register_movements')->where('id', $movementId)->first();
                $audit->record($request, 'register.payment_movement', 'cash_register_movement', $movementId, $after?->reference, null, $after, [
                    'session_id' => $session->id,
                    'payment_method' => $method->name,
                ]);
            });
        } catch (Throwable $e) {
            return back()->withErrors(['movement' => $e->getMessage()]);
        }

        return back()->with('success', 'Register movement recorded.');
    }

    public function close(Request $request, CashRegisterService $registers, PostingGuard $guard, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'counted_balances' => ['required', 'array'],
            'counted_balances.*' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:1500'],
            '_operation_id' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $registers, $guard, $audit): void {
                $session = $registers->requireOpenSession($request->user()?->id);
                $before = DB::table('cash_register_sessions')->where('id', $session->id)->lockForUpdate()->first();
                if (! $before || $before->status !== 'open') {
                    throw new RuntimeException('Register is already closed or unavailable.');
                }

                $guard->claim($request, 'register.close', 'cash_register_session', $session->id);
                $balances = DB::table('cash_register_session_balances')
                    ->where('cash_register_session_id', $session->id)
                    ->lockForUpdate()
                    ->get();

                $cashMethod = $registers->cashMethod();
                $cashExpected = 0.0;
                $cashCounted = 0.0;
                $cashVariance = 0.0;
                $closingSnapshot = [];

                foreach ($balances as $balance) {
                    $method = DB::table('payment_methods')->where('id', $balance->payment_method_id)->first();
                    if (! $method) {
                        continue;
                    }

                    if (! array_key_exists((string) $balance->payment_method_id, $validated['counted_balances'])
                        && ! array_key_exists($balance->payment_method_id, $validated['counted_balances'])) {
                        throw new RuntimeException("Enter the closing balance for {$method->name}.");
                    }

                    $expected = $registers->expectedBalance($session->id, (int) $balance->payment_method_id);
                    $counted = round((float) ($validated['counted_balances'][$balance->payment_method_id] ?? 0), 2);
                    $variance = round($counted - $expected, 2);

                    DB::table('cash_register_session_balances')->where('id', $balance->id)->update([
                        'expected_closing_balance' => $expected,
                        'counted_closing_balance' => $counted,
                        'variance' => $variance,
                        'updated_at' => now(),
                    ]);

                    $closingSnapshot[$method->name] = [
                        'expected' => $expected,
                        'counted' => $counted,
                        'variance' => $variance,
                    ];

                    if ((int) $method->id === (int) $cashMethod->id) {
                        $cashExpected = $expected;
                        $cashCounted = $counted;
                        $cashVariance = $variance;
                    }
                }

                DB::table('cash_register_sessions')->where('id', $session->id)->update([
                    'expected_cash' => $cashExpected,
                    'counted_cash' => $cashCounted,
                    'variance' => $cashVariance,
                    'closed_at' => now(),
                    'closed_by' => $request->user()?->id,
                    'status' => 'closed',
                    'closing_notes' => $validated['closing_notes'] ?? null,
                    'updated_at' => now(),
                ]);

                $after = DB::table('cash_register_sessions')->where('id', $session->id)->first();
                $audit->record($request, 'register.closed', 'cash_register_session', $session->id, $before->reference, $before, $after, [
                    'closing_balances' => $closingSnapshot,
                ]);
            });
        } catch (Throwable $e) {
            return back()->withErrors(['close' => $e->getMessage()]);
        }

        return back()->with('success', 'Register closed and all payment-method balances reconciled.');
    }

    public function storePaymentMethod(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:mobile_money,bank,card,other'],
            'account_name' => ['nullable', 'string', 'max:160'],
            'account_number' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $baseCode = Str::slug($validated['name']);
        $code = $baseCode !== '' ? $baseCode : 'payment-method';
        $suffix = 2;
        while (DB::table('payment_methods')->where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        $id = DB::table('payment_methods')->insertGetId([
            'name' => $validated['name'],
            'code' => $code,
            'type' => $validated['type'],
            'account_name' => $validated['account_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'is_cash' => false,
            'is_system' => false,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) DB::table('payment_methods')->max('sort_order') + 1,
            'created_by' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $after = DB::table('payment_methods')->where('id', $id)->first();
        $audit->record($request, 'payment_method.created', 'payment_method', $id, $code, null, $after);

        return back()->with('success', 'Payment method added. It will be included the next time a register is opened.');
    }

    public function updatePaymentMethod(Request $request, int $method, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:mobile_money,bank,card,other'],
            'account_name' => ['nullable', 'string', 'max:160'],
            'account_number' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $before = DB::table('payment_methods')->where('id', $method)->first();
        abort_unless($before, 404);

        if ($before->is_system || $before->is_cash) {
            return back()->withErrors(['payment_method' => 'Cash is a built-in payment method and cannot be changed or disabled.']);
        }

        DB::table('payment_methods')->where('id', $method)->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'account_name' => $validated['account_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'is_active' => (bool) $validated['is_active'],
            'updated_at' => now(),
        ]);

        $after = DB::table('payment_methods')->where('id', $method)->first();
        $audit->record($request, 'payment_method.updated', 'payment_method', $method, $before->code, $before, $after);

        return back()->with('success', 'Payment method updated. Open shifts keep the methods they started with.');
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
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'cash_register_movements.payment_method_id')
            ->where('cash_register_session_id', $sessionId)
            ->select('cash_register_movements.*', 'users.name as created_by_name', 'payment_methods.name as payment_method_name')
            ->orderBy('cash_register_movements.id')
            ->get();

        $sales = DB::table('pos_sales')->where('cash_register_session_id', $sessionId)->get();
        $returns = DB::table('pos_sale_returns')->where('cash_register_session_id', $sessionId)->get();
        $methodBalances = collect($registers->sessionBalances($sessionId));
        $cashMethod = $registers->cashMethod();
        $cashBalance = $methodBalances->first(fn ($balance) => (int) $balance->payment_method_id === (int) $cashMethod->id);

        $cashSales = (float) $movements->where('payment_method_id', $cashMethod->id)->where('type', 'cash_sale')->where('direction', 'in')->sum('amount');
        $nonCashSales = (float) $sales->filter(fn ($sale) => (int) ($sale->payment_method_id ?? 0) !== (int) $cashMethod->id)->sum('total');
        $cashRefunds = (float) $movements->where('payment_method_id', $cashMethod->id)->filter(fn ($m) => in_array($m->type, ['cash_refund', 'cash_void_refund'], true) && $m->direction === 'out')->sum('amount');
        $cashIn = (float) $movements->where('payment_method_id', $cashMethod->id)->where('direction', 'in')->where('type', 'cash_in')->sum('amount');
        $cashOut = (float) $movements->where('payment_method_id', $cashMethod->id)->where('direction', 'out')->where('type', 'cash_out')->sum('amount');
        $expenseCashOut = (float) $movements->where('payment_method_id', $cashMethod->id)->where('direction', 'out')->where('type', 'expense')->sum('amount');
        $expectedCash = $cashBalance ? (float) $cashBalance->expected_closing_balance : (float) ($session->expected_cash ?? 0);

        return [
            'session' => $session,
            'movements' => $movements,
            'sales' => $sales,
            'returns' => $returns,
            'method_balances' => $methodBalances->values(),
            'summary' => [
                'cash_sales' => $cashSales,
                'non_cash_sales' => $nonCashSales,
                'cash_refunds' => $cashRefunds,
                'manual_cash_in' => $cashIn,
                'manual_cash_out' => $cashOut,
                'expense_cash_out' => $expenseCashOut,
                'expected_cash' => $expectedCash,
                'counted_cash' => $cashBalance?->counted_closing_balance !== null ? (float) $cashBalance->counted_closing_balance : null,
                'variance' => $cashBalance?->variance !== null ? (float) $cashBalance->variance : null,
                'total_variance' => (float) $methodBalances->sum(fn ($balance) => (float) ($balance->variance ?? 0)),
            ],
        ];
    }
}
