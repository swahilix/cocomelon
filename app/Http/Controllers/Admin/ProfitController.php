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
                ->get(['id', 'subtotal', 'discount', 'total', 'created_by', 'created_at']);

            $posReturns = DB::table('pos_sale_returns')
                ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->get(['id', 'pos_sale_id', 'refund_amount', 'return_date']);
        }

        $onlineOrders = collect();
        if ($source !== 'pos') {
            $onlineOrders = Order::query()
                ->where('status', '!=', 'cancelled')
                ->whereNotIn('payment_status', ['failed', 'refunded'])
                ->where(function ($q): void {
                    $q->where('status', 'completed')->orWhere('payment_status', 'paid');
                })
                ->whereBetween('created_at', [$from, $to])
                ->get(['id', 'subtotal', 'shipping_total', 'discount_total', 'grand_total', 'created_at']);
        }

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

        $returnLines = $posReturnIds->isEmpty() ? collect() : DB::table('pos_sale_return_items')
            ->join('pos_sale_returns', 'pos_sale_returns.id', '=', 'pos_sale_return_items.pos_sale_return_id')
            ->join('pos_sale_items', 'pos_sale_items.id', '=', 'pos_sale_return_items.pos_sale_item_id')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_returns.pos_sale_id')
            ->leftJoin('products', 'products.id', '=', 'pos_sale_return_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('pos_sale_return_items.pos_sale_return_id', $posReturnIds)
            ->get([
                'pos_sale_return_items.pos_sale_return_id', 'pos_sale_return_items.pos_sale_item_id', 'pos_sale_return_items.product_id',
                'pos_sale_return_items.quantity', 'pos_sale_return_items.refund_amount', 'pos_sale_return_items.restock',
                'pos_sale_items.product_name', 'pos_sale_items.unit_cost', 'pos_sale_returns.pos_sale_id', 'pos_sale_returns.return_date',
                'pos_sales.created_by', 'categories.name as category_name',
            ]);

        $onlineLines = $onlineOrderIds->isEmpty() ? collect() : DB::table('order_items')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('order_items.order_id', $onlineOrderIds)
            ->get([
                'order_items.id', 'order_items.order_id', 'order_items.product_id', 'order_items.product_name',
                'order_items.quantity', 'order_items.unit_price', 'order_items.unit_cost', 'order_items.line_total',
                'categories.name as category_name',
            ]);

        $posRevenue = (float) $posSales->sum('total') - (float) $posReturns->sum('refund_amount');
        $onlineRevenue = (float) $onlineOrders->sum('grand_total');
        $posCogs = (float) $posLines->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost)
            - (float) $returnLines->where('restock', true)->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost);
        $onlineCogs = (float) $onlineLines->sum(fn ($line) => (int) $line->quantity * (float) ($line->unit_cost ?? 0));
        $cogs = $posCogs + $onlineCogs;

        $revenue = $posRevenue + $onlineRevenue;
        $grossProfit = $revenue - $cogs;
        $expenseRows = DB::table('expenses')
            ->where('status', 'posted')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get(['expense_date', 'amount']);
        $expenses = (float) $expenseRows->sum('amount');
        $netResult = $grossProfit - $expenses;

        $products = $this->productBreakdown($posLines, $posSales, $returnLines, $onlineLines, $onlineOrders);
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
                'margin' => $revenue != 0 ? ($profit / $revenue) * 100 : 0,
            ];
        })->sortByDesc('gross_profit')->values();

        $cashiers = $this->cashierBreakdown($posSales, $posLines, $returnLines);
        $daily = $this->dailyBreakdown($posSales, $posLines, $posReturns, $returnLines, $onlineOrders, $onlineLines, $expenseRows);

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
            'cashiers' => $cashiers,
            'daily' => $daily,
            'costHealth' => [
                'products_without_cost' => DB::table('products')->where('track_stock', true)->where('average_cost', '<=', 0)->count(),
                'historical_online_lines_without_cost' => DB::table('order_items')->whereNull('unit_cost')->count(),
            ],
        ]);
    }

    private function productBreakdown(Collection $posLines, Collection $posSales, Collection $returnLines, Collection $onlineLines, Collection $onlineOrders): Collection
    {
        $rows = collect();
        $posSaleMap = $posSales->keyBy('id');

        foreach ($posLines as $line) {
            $sale = $posSaleMap->get($line->pos_sale_id);
            $factor = $sale && (float) $sale->subtotal > 0 ? (float) $sale->total / (float) $sale->subtotal : 1;
            $rows->push([
                'key' => 'p:'.($line->product_id ?: $line->product_name),
                'product_id' => $line->product_id,
                'product' => $line->product_name,
                'category' => $line->category_name ?: 'Uncategorized',
                'units' => (int) $line->quantity,
                'revenue' => (float) $line->line_total * $factor,
                'cogs' => (int) $line->quantity * (float) $line->unit_cost,
            ]);
        }

        foreach ($returnLines as $line) {
            $rows->push([
                'key' => 'p:'.($line->product_id ?: $line->product_name),
                'product_id' => $line->product_id,
                'product' => $line->product_name,
                'category' => $line->category_name ?: 'Uncategorized',
                'units' => -1 * (int) $line->quantity,
                'revenue' => -1 * (float) $line->refund_amount,
                'cogs' => (bool) $line->restock ? -1 * (int) $line->quantity * (float) $line->unit_cost : 0,
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
                'margin' => $revenue != 0 ? ($profit / $revenue) * 100 : 0,
            ];
        })->values();
    }

    private function cashierBreakdown(Collection $posSales, Collection $posLines, Collection $returnLines): Collection
    {
        $userIds = $posSales->pluck('created_by')->merge($returnLines->pluck('created_by'))->filter()->unique();
        $names = $userIds->isEmpty() ? collect() : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');
        $linesBySale = $posLines->groupBy('pos_sale_id');
        $rows = collect();

        foreach ($posSales as $sale) {
            $saleLines = $linesBySale->get($sale->id, collect());
            $revenue = (float) $sale->total;
            $cogs = (float) $saleLines->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost);
            $rows->push(['user_id' => $sale->created_by, 'revenue' => $revenue, 'cogs' => $cogs]);
        }

        foreach ($returnLines as $line) {
            $rows->push([
                'user_id' => $line->created_by,
                'revenue' => -1 * (float) $line->refund_amount,
                'cogs' => (bool) $line->restock ? -1 * (int) $line->quantity * (float) $line->unit_cost : 0,
            ]);
        }

        return $rows->groupBy(fn ($row) => $row['user_id'] ?: 0)->map(function (Collection $group, $userId) use ($names): array {
            $revenue = (float) $group->sum('revenue');
            $cogs = (float) $group->sum('cogs');
            $profit = $revenue - $cogs;
            return [
                'cashier' => $userId ? ($names->get($userId) ?: 'Unknown user') : 'Unassigned / legacy',
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross_profit' => $profit,
                'margin' => $revenue != 0 ? ($profit / $revenue) * 100 : 0,
            ];
        })->sortByDesc('gross_profit')->values();
    }

    private function dailyBreakdown(Collection $posSales, Collection $posLines, Collection $posReturns, Collection $returnLines, Collection $onlineOrders, Collection $onlineLines, Collection $expenses): Collection
    {
        $events = collect();
        $posLinesBySale = $posLines->groupBy('pos_sale_id');
        $returnLinesByReturn = $returnLines->groupBy('pos_sale_return_id');
        $onlineLinesByOrder = $onlineLines->groupBy('order_id');

        foreach ($posSales as $sale) {
            $events->push([
                'date' => Carbon::parse($sale->created_at)->toDateString(),
                'revenue' => (float) $sale->total,
                'cogs' => (float) $posLinesBySale->get($sale->id, collect())->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost),
                'expenses' => 0,
            ]);
        }

        foreach ($posReturns as $return) {
            $lines = $returnLinesByReturn->get($return->id, collect());
            $events->push([
                'date' => Carbon::parse($return->return_date)->toDateString(),
                'revenue' => -1 * (float) $return->refund_amount,
                'cogs' => -1 * (float) $lines->where('restock', true)->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost),
                'expenses' => 0,
            ]);
        }

        foreach ($onlineOrders as $order) {
            $events->push([
                'date' => Carbon::parse($order->created_at)->toDateString(),
                'revenue' => (float) $order->grand_total,
                'cogs' => (float) $onlineLinesByOrder->get($order->id, collect())->sum(fn ($line) => (int) $line->quantity * (float) ($line->unit_cost ?? 0)),
                'expenses' => 0,
            ]);
        }

        foreach ($expenses as $expense) {
            $events->push([
                'date' => Carbon::parse($expense->expense_date)->toDateString(),
                'revenue' => 0,
                'cogs' => 0,
                'expenses' => (float) $expense->amount,
            ]);
        }

        return $events->groupBy('date')->map(function (Collection $group, string $date): array {
            $revenue = (float) $group->sum('revenue');
            $cogs = (float) $group->sum('cogs');
            $expenses = (float) $group->sum('expenses');
            $grossProfit = $revenue - $cogs;
            return [
                'date' => $date,
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'expenses' => $expenses,
                'net_result' => $grossProfit - $expenses,
            ];
        })->sortKeysDesc()->values();
    }
}
