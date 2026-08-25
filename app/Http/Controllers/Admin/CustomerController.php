<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $customers = DB::table('pos_customers')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('tin', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(250)
            ->get();

        $accounts = User::query()
            ->where('is_admin', false)
            ->whereNotNull('phone')
            ->get(['id', 'name', 'phone', 'created_at'])
            ->keyBy(fn (User $user): string => $this->normalizePhoneQuietly($user->phone) ?? 'user-'.$user->id);

        $salesByCustomer = $this->salesByCustomer($customers->pluck('id')->map(fn ($id) => (int) $id)->all());
        $salesByPhone = $this->salesByPhone();
        $ordersByPhone = $this->ordersByPhone();
        $installmentsByPhone = $this->installmentsByPhone();

        $rows = $customers->map(function ($customer) use ($accounts, $salesByCustomer, $salesByPhone, $ordersByPhone, $installmentsByPhone): array {
            $phone = $this->normalizePhoneQuietly($customer->phone);
            $directSales = $salesByCustomer->get((int) $customer->id);
            $phoneSales = $phone ? $salesByPhone->get($phone) : null;
            $orders = $phone ? $ordersByPhone->get($phone) : null;
            $installments = $phone ? $installmentsByPhone->get($phone) : null;
            $lastActivity = collect([
                $customer->updated_at,
                $directSales['last_at'] ?? null,
                $phoneSales['last_at'] ?? null,
                $orders['last_at'] ?? null,
                $installments['last_at'] ?? null,
            ])->filter()->sortDesc()->first();

            return [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'tin' => $customer->tin ?? null,
                'notes' => $customer->notes,
                'registered' => $phone ? $accounts->has($phone) : false,
                'sales_count' => (int) (($directSales['count'] ?? 0) + ($phoneSales['count'] ?? 0)),
                'sales_total' => round((float) (($directSales['total'] ?? 0) + ($phoneSales['total'] ?? 0)), 2),
                'order_count' => (int) ($orders['count'] ?? 0),
                'order_total' => round((float) ($orders['total'] ?? 0), 2),
                'installment_count' => (int) ($installments['count'] ?? 0),
                'installment_outstanding' => round((float) ($installments['outstanding'] ?? 0), 2),
                'last_activity' => $lastActivity,
                'created_at' => $customer->created_at,
            ];
        })->values();

        $installmentSummary = Schema::hasTable('installment_plans')
            ? DB::table('installment_plans')->whereIn('status', ['active', 'paid'])->get(['net_total', 'paid_amount'])
            : collect();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $rows,
            'filters' => ['search' => $search],
            'summary' => [
                'customers' => DB::table('pos_customers')->count(),
                'registered' => User::query()->where('is_admin', false)->whereNotNull('phone')->count(),
                'pos_sales' => Schema::hasTable('pos_sales') ? (float) DB::table('pos_sales')->sum('total') : 0,
                'outstanding_installments' => round((float) $installmentSummary->sum(fn ($plan) => max(0, (float) $plan->net_total - (float) $plan->paid_amount)), 2),
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        $customers = DB::table('pos_customers')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'phone', 'email', 'tin'])
            ->filter(fn ($customer): bool => $this->normalizePhoneQuietly($customer->phone) !== null)
            ->map(fn ($customer): array => [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'phone' => $this->normalizePhoneQuietly($customer->phone),
                'email' => $customer->email,
                'tin' => $customer->tin ?? null,
            ])
            ->values();

        return response()->json(['customers' => $customers]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCustomer($request);
        $phone = $this->normalizePhoneInput($validated['phone'] ?? null);
        $this->ensureUniquePhone($phone);

        DB::table('pos_customers')->insert([
            'name' => trim($validated['name']),
            'phone' => $phone,
            'email' => $this->nullableTrim($validated['email'] ?? null),
            'tin' => $this->nullableTrim($validated['tin'] ?? null),
            'notes' => $this->nullableTrim($validated['notes'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Customer added successfully.');
    }

    public function update(Request $request, int $customer): RedirectResponse
    {
        $existing = DB::table('pos_customers')->where('id', $customer)->first();
        abort_unless($existing, 404);

        $validated = $this->validateCustomer($request);
        $phone = $this->normalizePhoneInput($validated['phone'] ?? null);
        $this->ensureUniquePhone($phone, $customer);
        $oldPhone = $this->normalizePhoneQuietly($existing->phone);

        DB::transaction(function () use ($customer, $existing, $validated, $phone, $oldPhone): void {
            DB::table('pos_customers')->where('id', $customer)->update([
                'name' => trim($validated['name']),
                'phone' => $phone,
                'email' => $this->nullableTrim($validated['email'] ?? null),
                'tin' => $this->nullableTrim($validated['tin'] ?? null),
                'notes' => $this->nullableTrim($validated['notes'] ?? null),
                'updated_at' => now(),
            ]);

            if ($oldPhone) {
                $account = User::query()
                    ->where('is_admin', false)
                    ->whereNotNull('phone')
                    ->get(['id', 'phone'])
                    ->first(fn (User $user): bool => $this->normalizePhoneQuietly($user->phone) === $oldPhone);

                if ($account) {
                    $account->forceFill([
                        'name' => trim($validated['name']),
                        'phone' => $phone ?: $account->phone,
                    ])->save();
                }

                if (Schema::hasTable('installment_plans')) {
                    $planIds = DB::table('installment_plans')
                        ->whereIn('status', ['active', 'paid'])
                        ->whereNotNull('customer_phone')
                        ->get(['id', 'customer_phone'])
                        ->filter(fn ($plan): bool => $this->normalizePhoneQuietly($plan->customer_phone) === $oldPhone)
                        ->pluck('id');

                    if ($planIds->isNotEmpty()) {
                        DB::table('installment_plans')->whereIn('id', $planIds)->update([
                            'customer_name' => trim($validated['name']),
                            'customer_phone' => $phone ?: $existing->phone,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Customer details updated.');
    }

    private function validateCustomer(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function ensureUniquePhone(?string $phone, ?int $exceptId = null): void
    {
        if (! $phone) {
            return;
        }

        $duplicate = DB::table('pos_customers')
            ->whereNotNull('phone')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->get(['id', 'phone'])
            ->first(fn ($customer): bool => $this->normalizePhoneQuietly($customer->phone) === $phone);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'phone' => 'A customer with this phone number already exists.',
            ]);
        }
    }

    private function salesByCustomer(array $ids): Collection
    {
        if ($ids === [] || ! Schema::hasTable('pos_sales') || ! Schema::hasColumn('pos_sales', 'customer_id')) {
            return collect();
        }

        return DB::table('pos_sales')
            ->whereIn('customer_id', $ids)
            ->select('customer_id', DB::raw('COUNT(*) as aggregate_count'), DB::raw('COALESCE(SUM(total), 0) as aggregate_total'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('customer_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->customer_id => [
                'count' => (int) $row->aggregate_count,
                'total' => (float) $row->aggregate_total,
                'last_at' => $row->last_at,
            ]]);
    }

    private function salesByPhone(): Collection
    {
        if (! Schema::hasTable('pos_sales')) {
            return collect();
        }

        return DB::table('pos_sales')
            ->whereNull('customer_id')
            ->whereNotNull('customer_phone')
            ->get(['customer_phone', 'total', 'created_at'])
            ->groupBy(fn ($sale): string => $this->normalizePhoneQuietly($sale->customer_phone) ?? 'invalid')
            ->reject(fn ($rows, $phone): bool => $phone === 'invalid')
            ->map(fn ($rows): array => [
                'count' => $rows->count(),
                'total' => (float) $rows->sum('total'),
                'last_at' => $rows->max('created_at'),
            ]);
    }

    private function ordersByPhone(): Collection
    {
        if (! Schema::hasTable('orders')) {
            return collect();
        }

        return DB::table('orders')
            ->whereNotNull('phone')
            ->get(['phone', 'grand_total', 'created_at'])
            ->groupBy(fn ($order): string => $this->normalizePhoneQuietly($order->phone) ?? 'invalid')
            ->reject(fn ($rows, $phone): bool => $phone === 'invalid')
            ->map(fn ($rows): array => [
                'count' => $rows->count(),
                'total' => (float) $rows->sum('grand_total'),
                'last_at' => $rows->max('created_at'),
            ]);
    }

    private function installmentsByPhone(): Collection
    {
        if (! Schema::hasTable('installment_plans')) {
            return collect();
        }

        return DB::table('installment_plans')
            ->whereNotNull('customer_phone')
            ->get(['customer_phone', 'status', 'net_total', 'paid_amount', 'created_at', 'updated_at'])
            ->groupBy(fn ($plan): string => $this->normalizePhoneQuietly($plan->customer_phone) ?? 'invalid')
            ->reject(fn ($rows, $phone): bool => $phone === 'invalid')
            ->map(fn ($rows): array => [
                'count' => $rows->count(),
                'outstanding' => (float) $rows->whereIn('status', ['active', 'paid'])->sum(fn ($plan) => max(0, (float) $plan->net_total - (float) $plan->paid_amount)),
                'last_at' => $rows->max('updated_at') ?: $rows->max('created_at'),
            ]);
    }

    private function normalizePhoneInput(?string $input): ?string
    {
        if (blank($input)) {
            return null;
        }

        $phone = $this->normalizePhoneQuietly($input);
        if (! $phone) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid Tanzania phone number, for example 0712 345 678.',
            ]);
        }

        return $phone;
    }

    private function normalizePhoneQuietly(?string $input): ?string
    {
        if (! $input) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        return preg_match('/^255\d{9}$/', $digits) ? '+'.$digits : null;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
