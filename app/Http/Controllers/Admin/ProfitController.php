<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfitController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'source' => ['nullable', 'in:all,pos,online'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        $source = $validated['source'] ?? 'all';

        $posSales = collect();
        $posReturns = collect();
        if ($source !== 'online') {
            $posSales = DB::table('pos_sales')
                ->where('status', '!=', 'voided')
                ->whereBetween('created_at', [$from, $to])
                ->get(['id', 'subtotal', 'discount', 'total', 'created_at']);

            $posReturns = DB::table('pos_sale_returns')
                ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->get(['id', 'pos_sale_id', 'refund_amount', 'return_date']);
        }

        $onlineOrders = collect();
        if ($source !== 'pos') {
            $onlineOrders = Order::query()
                ->where('status', '!=', 'cancelled')
                ->where(function ($q): void {
                    $q->where('status', 'completed')->orWhere('payment_status', 'paid');
                })
                ->whereBetween('created_at', [$from, $to])
                ->get(['id', 'subtotal', 'shipping_total', 'discount_total', 'grand_total', 'created_at']);
        }

        $posRevenue = (float) $posSales->sum('total') - (float) $posReturns->sum('refund_amount');
        $onlineRevenue = (float) $onlineOrders->sum('grand_total');

        $posSaleIds = $posSales->pluck('id');
        $posReturnIds = $posReturns->pluck('id');
        $onlineOrderIds = $onlineOrders->pluck('id');

        $posLines = $posSaleIds->isEmpty() ? collect() : DB::table('pos_sale_items')
            ->leftJoin('products', 'products.id', '=', 'pos_sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('pos_sale_items.pos_sale_id', $posSaleIds)
            ->get([
                'pos_sale_items.id', 'pos_sale_items.pos_sale_id', 'pos_sale_items.product_id', 'pos_sale_items.product_name',
                'pos_sale_items.quantity', 'pos_sale_items.unit_price', 'pos_sale_items.unit_cost', 'pos_sale_items.line_total',
                'categories.name as category_name',
            ]);

        $restockedReturns = $posReturnIds->isEmpty() ? collect() : DB::table('pos_sale_return_items')
            ->join('pos_sale_returns', 'pos_sale_returns.id', '=', 'pos_sale_return_items.pos_sale_return_id')
            ->join('pos_sale_items', 'pos_sale_items.id', '=', 'pos_sale_return_items.pos_sale_item_id')
            ->whereIn('pos_sale_return_items.pos_sale_return_id', $posReturnIds)
            ->where('pos_sale_return_items.restock', true)
            ->get([
                'pos_sale_return_items.pos_sale_item_id', 'pos_sale_return_items.product_id', 'pos_sale_return_items.quantity',
                'pos_sale_return_items.refund_amount', 'pos_sale_items.unit_cost',
            ]);

        $allReturnLines = $posReturnIds->isEmpty() ? collect() : DB::table('pos_sale_return_items')
            ->whereIn('pos_sale_return_id', $posReturnIds)
            ->get(['pos_sale_item_id', 'product_id', 'quantity', 'refund_amount', 'restock']);

        $onlineLines = $onlineOrderIds->isEmpty() ? collect() : DB::table('order_items')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('order_items.order_id', $onlineOrderIds)
            ->get([
                'order_items.id', 'order_items.order_id', 'order_items.product_id', 'order_items.product_name',
                'order_items.quantity', 'order_items.unit_price', 'order_items.unit_cost', 'order_items.line_total',
                'categories.name as category_name',
            ]);

        $posCogs = (float) $posLines->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost)
            - (float) $restockedReturns->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost);
        $onlineCogs = (float) $onlineLines->sum(fn ($line) => (int) $line->quantity * (float) ($line->unit_cost ?? 0));
        $cogs = max(0, $posCogs + $onlineCogs);

        $revenue = $posRevenue + $onlineRevenue;
        $grossProfit = $revenue - $cogs;
        $expenses = (float) DB::table('expenses')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
        $netResult = $grossProfit - $expenses;

        $products = $this->productBreakdown($posLines, $posSales, $allReturnLines, $onlineLines, $onlineOrders);
        $categories = $products->groupBy('category')->map(function (Collection $rows, string $category): array {
            $revenue = (float) $rows->sum('revenue');
            $cogs = (float) $rows->sum('cogs');
            $profit = $revenue - $cogs;
            return [
                'category' => $category,
                'units' => (int) $rows->sum('units'),
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross_profit' => $profit,
                'margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
            ];
        })->sortByDesc('gross_profit')->values();

        return Inertia::render('Admin/Profit/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'source' => $source,
            ],
            'summary' => [
                'revenue' => $revenue,
                'pos_revenue' => $posRevenue,
                'online_revenue' => $onlineRevenue,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'gross_margin' => $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0,
                'expenses' => $expenses,
                'net_result' => $netResult,
                'refunds' => (float) $posReturns->sum('refund_amount'),
            ],
            'products' => $products->sortByDesc('gross_profit')->values()->take(100),
            'categories' => $categories,
            'costHealth' => [
                'products_without_cost' => DB::table('products')->where('track_stock', true)->where('average_cost', '<=', 0)->count(),
                'historical_online_lines_without_cost' => DB::table('order_items')->whereNull('unit_cost')->count(),
                'online_costing_started' => true,
            ],
        ]);
    }

    private function productBreakdown(Collection $posLines, Collection $posSales, Collection $returnLines, Collection $onlineLines, Collection $onlineOrders): Collection
    {
        $rows = collect();
        $posSaleMap = $posSales->keyBy('id');
        $returnByItem = $returnLines->groupBy('pos_sale_item_id');

        foreach ($posLines as $line) {
            $sale = $posSaleMap->get($line->pos_sale_id);
            $factor = $sale && (float) $sale->subtotal > 0 ? (float) $sale->total / (float) $sale->subtotal : 1;
            $refund = (float) ($returnByItem->get($line->id)?->sum('refund_amount') ?? 0);
            $returnedQty = (int) ($returnByItem->get($line->id)?->sum('quantity') ?? 0);
            $restockedQty = (int) ($returnByItem->get($line->id)?->where('restock', true)->sum('quantity') ?? 0);
            $revenue = max(0, ((float) $line->line_total * $factor) - $refund);
            $cogs = max(0, (((int) $line->quantity - $restockedQty) * (float) $line->unit_cost));
            $rows->push([
                'key' => 'p:'.($line->product_id ?: $line->product_name),
                'product_id' => $line->product_id,
                'product' => $line->product_name,
                'category' => $line->category_name ?: 'Uncategorized',
                'units' => max(0, (int) $line->quantity - $returnedQty),
                'revenue' => $revenue,
                'cogs' => $cogs,
            ]);
        }

        $onlineMap = $onlineOrders->keyBy('id');
        foreach ($onlineLines as $line) {
            $order = $onlineMap->get($line->order_id);
            $factor = $order && (float) $order->subtotal > 0
                ? max(0, ((float) $order->subtotal - (float) $order->discount_total) / (float) $order->subtotal)
                : 1;
            $rows->push([
                'key' => 'p:'.($line->product_id ?: $line->product_name),
                'product_id' => $line->product_id,
                'product' => $line->product_name,
                'category' => $line->category_name ?: 'Uncategorized',
                'units' => (int) $line->quantity,
                'revenue' => (float) $line->line_total * $factor,
                'cogs' => (int) $line->quantity * (float) ($line->unit_cost ?? 0),
            ]);
        }

        return $rows->groupBy('key')->map(function (Collection $group): array {
            $first = $group->first();
            $revenue = (float) $group->sum('revenue');
            $cogs = (float) $group->sum('cogs');
            $profit = $revenue - $cogs;
            return [
                'product_id' => $first['product_id'],
                'product' => $first['product'],
                'category' => $first['category'],
                'units' => (int) $group->sum('units'),
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross_profit' => $profit,
                'margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
            ];
        })->values();
    }
}
