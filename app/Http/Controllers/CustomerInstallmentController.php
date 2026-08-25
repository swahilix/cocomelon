<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInstallmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->guardCustomer($request);

        $plans = $this->ownedPlansQuery($request)
            ->latest('id')
            ->get()
            ->map(fn ($plan) => $this->serializePlan($plan));

        return Inertia::render('Store/Account/Installments', ['plans' => $plans]);
    }

    public function show(Request $request, string $reference): Response
    {
        $this->guardCustomer($request);
        $plan = $this->ownedPlansQuery($request)->where('reference', $reference)->first();
        abort_unless($plan, 404);

        return Inertia::render('Store/Account/InstallmentShow', [
            'plan' => $this->serializePlan($plan),
            'items' => DB::table('installment_plan_items')->where('installment_plan_id', $plan->id)->orderBy('id')->get(),
            'payments' => DB::table('installment_payments')
                ->where('installment_plan_id', $plan->id)
                ->latest('id')
                ->get(['id', 'reference', 'amount', 'payment_method_name', 'external_reference', 'sms_status', 'sms_sent_at', 'created_at']),
        ]);
    }

    private function ownedPlansQuery(Request $request)
    {
        $user = $request->user();
        $local = $this->localPhoneDigits((string) $user->phone);

        return DB::table('installment_plans')->where(function ($query) use ($user, $local): void {
            $query->where('user_id', $user->id);
            if ($local) {
                $query->orWhereRaw($this->phoneDigitsSql('customer_phone').' LIKE ?', ['%'.$local]);
            }
        });
    }

    private function serializePlan(object $plan): array
    {
        $remaining = max(0, round((float) $plan->net_total - (float) $plan->paid_amount, 2));
        return [
            ...((array) $plan),
            'subtotal' => (float) $plan->subtotal,
            'discount_amount' => (float) $plan->discount_amount,
            'net_total' => (float) $plan->net_total,
            'paid_amount' => (float) $plan->paid_amount,
            'remaining_amount' => $remaining,
            'progress' => (float) $plan->net_total > 0 ? min(100, round(((float) $plan->paid_amount / (float) $plan->net_total) * 100, 1)) : 0,
        ];
    }

    private function guardCustomer(Request $request): void
    {
        abort_if(! $request->user() || $request->user()->is_admin, 403);
    }

    private function localPhoneDigits(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        return preg_match('/^255\d{9}$/', $digits) ? substr($digits, -9) : null;
    }

    private function phoneDigitsSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";
    }
}
