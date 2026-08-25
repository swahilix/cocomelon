<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerOtpSmsService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InstallmentController extends Controller
{
    private const STOCK_RESERVATION_PERCENT = 70.0;

    public function index(Request $request): Response
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $plans = DB::table('installment_plans')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->limit(150)
            ->get()
            ->map(fn ($plan) => $this->serializePlan($plan));

        $all = DB::table('installment_plans')->get(['status', 'net_total', 'paid_amount']);

        return Inertia::render('Admin/Installments/Index', [
            'plans' => $plans,
            'products' => Product::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'stock_quantity', 'track_stock'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->current_price,
                    'stock_quantity' => (int) $product->stock_quantity,
                    'track_stock' => (bool) $product->track_stock,
                ])
                ->values(),
            'paymentMethods' => $this->paymentMethods(),
            'filters' => ['status' => $status, 'search' => $search],
            'reservationPercent' => self::STOCK_RESERVATION_PERCENT,
            'summary' => [
                'plans' => $all->count(),
                'active' => $all->where('status', 'active')->count(),
                'fully_paid' => $all->whereIn('status', ['paid', 'released'])->count(),
                'released' => $all->where('status', 'released')->count(),
                'outstanding' => round((float) $all->whereIn('status', ['active', 'paid'])->sum(fn ($plan) => max(0, (float) $plan->net_total - (float) $plan->paid_amount)), 2),
            ],
        ]);
    }

    public function store(Request $request, InventoryService $inventory, CustomerOtpSmsService $sms): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'discount_type' => ['required', 'in:none,fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'initial_payment' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer'],
            'external_reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $phone = $this->normalizePhone($validated['customer_phone']);
        $initialPayment = round((float) ($validated['initial_payment'] ?? 0), 2);
        $paymentMethod = $initialPayment > 0
            ? $this->activePaymentMethod((int) ($validated['payment_method_id'] ?? 0))
            : null;

        if ($initialPayment > 0 && ! $paymentMethod) {
            throw ValidationException::withMessages(['payment_method_id' => 'Choose an active payment method for the initial payment.']);
        }

        $created = DB::transaction(function () use ($request, $validated, $phone, $initialPayment, $paymentMethod, $inventory): array {
            $quantities = collect($validated['items'])
                ->groupBy(fn (array $item) => (int) $item['product_id'])
                ->map(fn ($rows) => (int) $rows->sum(fn (array $row) => (int) $row['quantity']));

            $products = Product::query()
                ->whereIn('id', $quantities->keys()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $quantities->count()) {
                throw ValidationException::withMessages(['items' => 'One or more selected products are no longer available.']);
            }

            $lines = [];
            $subtotal = 0.0;

            foreach ($quantities as $productId => $quantity) {
                /** @var Product $product */
                $product = $products->get((int) $productId);

                if ($product->status !== 'active') {
                    throw ValidationException::withMessages(['items' => $product->name.' is not active.']);
                }

                if ($product->track_stock && (int) $product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough stock for {$product->name}. Available: {$product->stock_quantity}.",
                    ]);
                }

                $unitPrice = round((float) $product->current_price, 2);
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;
                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discountType = $validated['discount_type'];
            $discountValue = round((float) ($validated['discount_value'] ?? 0), 2);

            if ($discountType === 'percent' && $discountValue > 100) {
                throw ValidationException::withMessages(['discount_value' => 'Percentage discount cannot be greater than 100%.']);
            }

            $discountAmount = match ($discountType) {
                'fixed' => min($subtotal, $discountValue),
                'percent' => round($subtotal * ($discountValue / 100), 2),
                default => 0.0,
            };
            $netTotal = round($subtotal - $discountAmount, 2);

            if ($netTotal <= 0) {
                throw ValidationException::withMessages(['discount_value' => 'The discount cannot reduce the installment total to zero.']);
            }

            if ($initialPayment > $netTotal) {
                throw ValidationException::withMessages(['initial_payment' => 'Initial payment cannot exceed the net installment total.']);
            }

            $reference = $this->newPlanReference();
            $customer = $this->findCustomerByPhone($phone);
            $now = now();
            $planId = DB::table('installment_plans')->insertGetId([
                'reference' => $reference,
                'user_id' => $customer?->id,
                'customer_name' => trim($validated['customer_name']),
                'customer_phone' => $phone,
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'net_total' => $netTotal,
                'paid_amount' => 0,
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                DB::table('installment_plan_items')->insert([
                    'installment_plan_id' => $planId,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'unit_cost' => $product->average_cost !== null ? (float) $product->average_cost : null,
                    'reservation_movement_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $paymentId = null;
            if ($initialPayment > 0 && $paymentMethod) {
                $paymentId = $this->recordPaymentLocked(
                    $planId,
                    $reference,
                    $netTotal,
                    0,
                    $initialPayment,
                    $paymentMethod,
                    $validated['external_reference'] ?? null,
                    'Initial installment payment',
                    $request->user()?->id,
                );
            }

            $stockReserved = false;
            if ($initialPayment >= $this->reservationThreshold($netTotal)) {
                $stockReserved = $this->reservePlanStockLocked(
                    $planId,
                    $reference,
                    $inventory,
                    $request->user()?->id,
                    'initial_payment',
                );
            }

            return [
                'plan_id' => $planId,
                'reference' => $reference,
                'payment_id' => $paymentId,
                'stock_reserved' => $stockReserved,
            ];
        });

        $smsError = null;
        if ($created['payment_id']) {
            $smsError = $this->sendPaymentSms((int) $created['payment_id'], $sms);
        }

        $message = $created['stock_reserved']
            ? 'Installment plan created. Payment reached 70%, so the selected stock is now reserved.'
            : 'Installment plan created. Stock will be reserved when total payments reach 70% of the agreed amount.';

        $response = redirect('/admin/installments/'.$created['reference'])->with('success', $message);

        if ($smsError) {
            $response->with('error', 'Initial payment was recorded, but SMS could not be sent: '.$smsError);
        }

        return $response;
    }

    public function show(string $reference): Response
    {
        $plan = $this->planByReference($reference);

        return Inertia::render('Admin/Installments/Show', [
            'plan' => $this->serializePlan($plan),
            'items' => DB::table('installment_plan_items')->where('installment_plan_id', $plan->id)->orderBy('id')->get(),
            'payments' => DB::table('installment_payments')->where('installment_plan_id', $plan->id)->latest('id')->get(),
            'paymentMethods' => $this->paymentMethods(),
            'reservationPercent' => self::STOCK_RESERVATION_PERCENT,
        ]);
    }

    public function payment(Request $request, string $reference, CustomerOtpSmsService $sms, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer'],
            'external_reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $method = $this->activePaymentMethod((int) $validated['payment_method_id']);
        if (! $method) {
            throw ValidationException::withMessages(['payment_method_id' => 'Choose an active payment method.']);
        }

        $result = DB::transaction(function () use ($request, $validated, $reference, $method, $inventory): array {
            $plan = DB::table('installment_plans')->where('reference', $reference)->lockForUpdate()->first();
            abort_unless($plan, 404);

            if ($plan->status !== 'active') {
                throw ValidationException::withMessages(['amount' => 'Payments can only be posted to an active installment plan.']);
            }

            $remaining = round((float) $plan->net_total - (float) $plan->paid_amount, 2);
            $amount = round((float) $validated['amount'], 2);

            if ($amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the remaining balance of TZS '.number_format($remaining, 0).'.']);
            }

            $paidAfter = round((float) $plan->paid_amount + $amount, 2);
            $stockReserved = false;

            if ($paidAfter >= $this->reservationThreshold((float) $plan->net_total)) {
                $stockReserved = $this->reservePlanStockLocked(
                    (int) $plan->id,
                    $plan->reference,
                    $inventory,
                    $request->user()?->id,
                    'amount',
                );
            }

            $paymentId = $this->recordPaymentLocked(
                (int) $plan->id,
                $plan->reference,
                (float) $plan->net_total,
                (float) $plan->paid_amount,
                $amount,
                $method,
                $validated['external_reference'] ?? null,
                $validated['notes'] ?? null,
                $request->user()?->id,
            );

            return ['payment_id' => $paymentId, 'stock_reserved' => $stockReserved];
        });

        $smsError = $this->sendPaymentSms((int) $result['payment_id'], $sms);
        $message = $result['stock_reserved']
            ? 'Installment payment received. Total payments reached 70%, so the selected stock is now reserved.'
            : 'Installment payment received and balance updated.';
        $response = back()->with('success', $message);

        if ($smsError) {
            $response->with('error', 'Payment was saved, but the confirmation SMS failed: '.$smsError);
        }

        return $response;
    }

    public function resendSms(string $reference, int $payment, CustomerOtpSmsService $sms): RedirectResponse
    {
        $plan = $this->planByReference($reference);
        abort_unless(DB::table('installment_payments')->where('id', $payment)->where('installment_plan_id', $plan->id)->exists(), 404);

        $error = $this->sendPaymentSms($payment, $sms);

        return $error
            ? back()->with('error', 'SMS could not be sent: '.$error)
            : back()->with('success', 'Payment confirmation SMS sent again.');
    }

    public function release(Request $request, string $reference, InventoryService $inventory): RedirectResponse
    {
        DB::transaction(function () use ($request, $reference, $inventory): void {
            $plan = DB::table('installment_plans')->where('reference', $reference)->lockForUpdate()->first();
            abort_unless($plan, 404);

            $remaining = round((float) $plan->net_total - (float) $plan->paid_amount, 2);
            if ($remaining > 0 || $plan->status !== 'paid') {
                throw ValidationException::withMessages(['release' => 'The full installment balance must be paid before the items can be released.']);
            }

            $this->reservePlanStockLocked(
                (int) $plan->id,
                $plan->reference,
                $inventory,
                $request->user()?->id,
                'release',
            );

            DB::table('installment_plans')->where('id', $plan->id)->update([
                'status' => 'released',
                'released_at' => now(),
                'released_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Items marked as released to the customer.');
    }

    public function cancel(Request $request, string $reference, InventoryService $inventory): RedirectResponse
    {
        $returnedStock = DB::transaction(function () use ($request, $reference, $inventory): bool {
            $plan = DB::table('installment_plans')->where('reference', $reference)->lockForUpdate()->first();
            abort_unless($plan, 404);

            if ($plan->status !== 'active') {
                throw ValidationException::withMessages(['cancel' => 'Only an active installment plan can be cancelled.']);
            }

            if ((float) $plan->paid_amount > 0) {
                throw ValidationException::withMessages(['cancel' => 'This plan already has payments. Resolve any refund before cancelling it.']);
            }

            $returned = false;
            $items = DB::table('installment_plan_items')->where('installment_plan_id', $plan->id)->lockForUpdate()->get();
            foreach ($items as $item) {
                if ($item->reservation_movement_id === null || $item->return_movement_id !== null) {
                    continue;
                }

                $movement = $inventory->increase(
                    (int) $item->product_id,
                    (int) $item->quantity,
                    'installment_cancellation',
                    'CANCEL-'.$plan->reference,
                    null,
                    'Released from cancelled installment reservation.',
                    $request->user()?->id,
                    'installment_plan',
                    (int) $plan->id,
                    'Installment cancellation',
                );
                DB::table('installment_plan_items')->where('id', $item->id)->update([
                    'return_movement_id' => $movement->id,
                    'updated_at' => now(),
                ]);
                $returned = true;
            }

            DB::table('installment_plans')->where('id', $plan->id)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);

            return $returned;
        });

        return redirect('/admin/installments')->with(
            'success',
            $returnedStock ? 'Installment plan cancelled and reserved stock returned.' : 'Installment plan cancelled. No stock had been reserved yet.'
        );
    }

    private function reservePlanStockLocked(
        int $planId,
        string $planReference,
        InventoryService $inventory,
        ?int $userId,
        string $errorField,
    ): bool {
        $items = DB::table('installment_plan_items')
            ->where('installment_plan_id', $planId)
            ->lockForUpdate()
            ->get();

        $unreserved = $items->filter(fn ($item): bool => $item->reservation_movement_id === null);
        if ($unreserved->isEmpty()) {
            return false;
        }

        try {
            foreach ($unreserved as $item) {
                $movement = $inventory->decrease(
                    (int) $item->product_id,
                    (int) $item->quantity,
                    'installment_reservation',
                    $planReference,
                    null,
                    'Reserved after installment payments reached '.number_format(self::STOCK_RESERVATION_PERCENT, 0).'%.',
                    $userId,
                    'installment_plan',
                    $planId,
                    'Installment reservation threshold reached',
                );

                DB::table('installment_plan_items')->where('id', $item->id)->update([
                    'reservation_movement_id' => $movement->id,
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                $errorField => 'Payments have reached the 70% stock-reservation point, but the stock cannot be reserved: '.$exception->getMessage(),
            ]);
        }

        return true;
    }

    private function reservationThreshold(float $netTotal): float
    {
        return round($netTotal * (self::STOCK_RESERVATION_PERCENT / 100), 2);
    }

    private function recordPaymentLocked(
        int $planId,
        string $planReference,
        float $netTotal,
        float $paidBefore,
        float $amount,
        object $method,
        ?string $externalReference,
        ?string $notes,
        ?int $userId,
    ): int {
        $paidAfter = round($paidBefore + $amount, 2);
        $isPaid = $paidAfter >= round($netTotal, 2);
        $now = now();
        $paymentId = DB::table('installment_payments')->insertGetId([
            'installment_plan_id' => $planId,
            'reference' => $this->newPaymentReference($planReference),
            'amount' => $amount,
            'payment_method_id' => $method->id,
            'payment_method_name' => $method->name,
            'external_reference' => $externalReference,
            'notes' => $notes,
            'received_by' => $userId,
            'sms_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('installment_plans')->where('id', $planId)->update([
            'paid_amount' => $paidAfter,
            'status' => $isPaid ? 'paid' : 'active',
            'paid_at' => $isPaid ? $now : null,
            'updated_at' => $now,
        ]);

        return $paymentId;
    }

    private function sendPaymentSms(int $paymentId, CustomerOtpSmsService $sms): ?string
    {
        $payment = DB::table('installment_payments')
            ->join('installment_plans', 'installment_plans.id', '=', 'installment_payments.installment_plan_id')
            ->where('installment_payments.id', $paymentId)
            ->select(
                'installment_payments.id',
                'installment_payments.amount',
                'installment_payments.reference as payment_reference',
                'installment_plans.reference as plan_reference',
                'installment_plans.customer_phone',
                'installment_plans.net_total',
                'installment_plans.paid_amount',
                'installment_plans.status'
            )
            ->first();

        if (! $payment) {
            return 'Payment record was not found.';
        }

        $remaining = max(0, round((float) $payment->net_total - (float) $payment->paid_amount, 2));
        $reserved = (float) $payment->paid_amount >= $this->reservationThreshold((float) $payment->net_total);
        $message = 'Prinx: Payment of TZS '.number_format((float) $payment->amount, 0)
            .' received for '.$payment->plan_reference.'. Total paid TZS '.number_format((float) $payment->paid_amount, 0)
            .'. Remaining TZS '.number_format($remaining, 0).'.'
            .($remaining <= 0
                ? ' Your installment is fully paid. Please wait for item release confirmation.'
                : ($reserved ? ' Your item stock is now reserved.' : ' Stock will be reserved after total payments reach 70%.'));

        try {
            $sms->sendMessage((string) $payment->customer_phone, $message);
            DB::table('installment_payments')->where('id', $paymentId)->update([
                'sms_status' => 'sent',
                'sms_error' => null,
                'sms_sent_at' => now(),
                'updated_at' => now(),
            ]);
            return null;
        } catch (Throwable $exception) {
            Log::warning('Installment payment SMS failed.', [
                'payment_id' => $paymentId,
                'message' => $exception->getMessage(),
            ]);
            DB::table('installment_payments')->where('id', $paymentId)->update([
                'sms_status' => 'failed',
                'sms_error' => mb_substr($exception->getMessage(), 0, 1000),
                'updated_at' => now(),
            ]);
            return $exception->getMessage();
        }
    }

    private function serializePlan(object $plan): array
    {
        $remaining = max(0, round((float) $plan->net_total - (float) $plan->paid_amount, 2));
        $progress = (float) $plan->net_total > 0
            ? min(100, round(((float) $plan->paid_amount / (float) $plan->net_total) * 100, 1))
            : 0;

        return [
            ...((array) $plan),
            'subtotal' => (float) $plan->subtotal,
            'discount_value' => (float) $plan->discount_value,
            'discount_amount' => (float) $plan->discount_amount,
            'net_total' => (float) $plan->net_total,
            'paid_amount' => (float) $plan->paid_amount,
            'remaining_amount' => $remaining,
            'progress' => $progress,
            'reservation_threshold_percent' => self::STOCK_RESERVATION_PERCENT,
            'reservation_threshold_amount' => $this->reservationThreshold((float) $plan->net_total),
        ];
    }

    private function planByReference(string $reference): object
    {
        $plan = DB::table('installment_plans')->where('reference', $reference)->first();
        abort_unless($plan, 404);
        return $plan;
    }

    private function paymentMethods(): array
    {
        return DB::table('payment_methods')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'is_cash'])
            ->map(fn ($method): array => [
                'id' => (int) $method->id,
                'name' => $method->name,
                'code' => $method->code,
                'type' => $method->type,
                'is_cash' => (bool) $method->is_cash,
            ])
            ->values()
            ->all();
    }

    private function activePaymentMethod(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        return DB::table('payment_methods')->where('id', $id)->where('is_active', true)->first();
    }

    private function findCustomerByPhone(string $phone): ?User
    {
        return User::query()
            ->where('is_admin', false)
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->first(fn (User $user): bool => $this->phoneMatches($user->phone, $phone));
    }

    private function normalizePhone(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        if (! preg_match('/^255\d{9}$/', $digits)) {
            throw ValidationException::withMessages(['customer_phone' => 'Enter a valid Tanzania phone number, for example 0712 345 678.']);
        }

        return '+'.$digits;
    }

    private function phoneMatches(?string $left, string $right): bool
    {
        if (! $left) {
            return false;
        }

        try {
            return $this->normalizePhone($left) === $this->normalizePhone($right);
        } catch (ValidationException) {
            return false;
        }
    }

    private function newPlanReference(): string
    {
        do {
            $reference = 'INS-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        } while (DB::table('installment_plans')->where('reference', $reference)->exists());

        return $reference;
    }

    private function newPaymentReference(string $planReference): string
    {
        do {
            $reference = $planReference.'-P'.strtoupper(Str::random(5));
        } while (DB::table('installment_payments')->where('reference', $reference)->exists());

        return $reference;
    }
}
