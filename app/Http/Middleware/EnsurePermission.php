<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);
        abort_unless($permissions && $user->hasAnyPermission($permissions), 403, 'You do not have permission to perform this action.');

        if ($request->isMethod('post') && $request->is('admin/stock/pos') && in_array('use_pos', $permissions, true)) {
            return app(EnforcePosPricingPermissions::class)->handle($request, $next);
        }

        // Historical imports are reference-only records. They must never be
        // routed through live stock or live cash-register mutation actions.
        if ($request->isMethod('post') && $request->is('admin/stock/purchases/*/returns')) {
            $purchaseId = (int) $request->route('purchase');
            if ($purchaseId > 0 && DB::table('stock_purchases')->where('id', $purchaseId)->where('is_historical', true)->exists()) {
                return back()->withErrors([
                    'return' => 'This is a historical purchase. It is kept for reference only, so it cannot change today\'s physical stock.',
                ]);
            }
        }

        if ($request->isMethod('post') && ($request->is('admin/sales/pos/*/returns') || $request->is('admin/sales/pos/*/void'))) {
            $saleId = (int) $request->route('sale');
            if ($saleId > 0 && DB::table('pos_sales')->where('id', $saleId)->where('is_historical', true)->exists()) {
                return back()->withErrors([
                    'sale' => 'This is a historical sale. It is kept for reference only, so it cannot change current stock or today\'s cash register.',
                ]);
            }
        }

        return $next($request);
    }
}
