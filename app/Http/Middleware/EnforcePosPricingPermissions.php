<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePosPricingPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $discount = (float) $request->input('discount', 0);

        if ($discount > 0 && ! $user?->hasPermission('discount_pos')) {
            abort(403, 'You do not have permission to discount POS sales.');
        }

        if (! $user?->hasPermission('override_pos_price')) {
            $items = collect($request->input('items', []));
            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get((int) ($item['product_id'] ?? 0));
                if (! $product) continue;
                $submitted = round((float) ($item['unit_price'] ?? 0), 2);
                $allowed = round((float) $product->current_price, 2);
                if (abs($submitted - $allowed) > 0.009) {
                    abort(403, 'You do not have permission to override POS product prices.');
                }
            }
        }

        return $next($request);
    }
}
