<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PrepareHistoricalPurchaseImport
{
    public function handle(Request $request, Closure $next): Response
    {
        $paymentMethod = strtolower(trim((string) $request->input('payment_method', '')));
        $amountPaid = $request->input('amount_paid');

        if (($amountPaid === null || $amountPaid === '') && in_array($paymentMethod, ['credit', 'credit / unpaid'], true)) {
            $request->merge(['amount_paid' => 0]);
        }

        $response = $next($request);

        // Keep historical purchase status labels consistent with the normal
        // purchasing workflow used by supplier balance screens and reports.
        DB::table('stock_purchases')
            ->where('is_historical', true)
            ->where('payment_status', 'partial')
            ->update(['payment_status' => 'partially_paid']);

        return $response;
    }
}
