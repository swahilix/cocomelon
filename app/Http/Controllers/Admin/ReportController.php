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

class ReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $products = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereNull('products.deleted_at')
            ->select(
                'products.id', 'products.name', 'products.sku', 'products.stock_quantity', 'products.low_stock_threshold',
                'products.track_stock', 'products.average_cost', 'products.last_purchase_cost', 'products.price',
                'categories.name as category_name'
            )
            ->get();

        $inventoryValue = (float) $products->where('track_stock', true)
            ->sum(fn ($p) => max(0, (int) $p->stock_quantity) * (float) $p->average_cost);

        $movementRows = DB::table('stock_movements')
            ->whereBetween('created_at', [$from, $to])
            ->get(['product_id', 'type', 'quantity', 'created_at']);

        $soldUnitsByProduct = collect();
        $posSales = DB::table('pos_sales')
            ->where('status', '!=', 'voided')
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'total', 'payment_method', 'created_by', 'created_at']);
        $posSaleIds = $posSales->pluck('id');
        $posItems = $posSaleIds->isEmpty() ? collect() : DB::table('pos_sale_items')
            ->whereIn('pos_sale_id', $posSaleIds)
            ->get(['pos_sale_id', 'product_id', 'product_name', 'quantity', 'unit_cost', 'line_total']);
        foreach ($posItems as $item) {
            $soldUnitsByProduct[$item->product_id ?: 'name:'.$item->product_name] = ($soldUnitsByProduct[$item->product_id ?: 'name:'.$item->product_name] ?? 0) + (int) $item->quantity;
        }

        $onlineOrders = Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereNotIn('payment_status', ['failed', 'refunded'])
            ->where(function ($q): void {
                $q->where('status', 'completed')->orWhere('payment_status', 'paid');
            })
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'grand_total', 'created_at']);
        $onlineOrderIds = $onlineOrders->pluck('id');
        $onlineItems = $onlineOrderIds->isEmpty() ? collect() : DB::table('order_items')
            ->whereIn('order_id', $onlineOrderIds)
            ->get(['order_id', 'product_id', 'product_name', 'quantity', 'unit_cost', 'line_total']);
        foreach ($onlineItems as $item) {
            $key = $item->product_id ?: 'name:'.$item->product_name;
            $soldUnitsByProduct[$key] = ($soldUnitsByProduct[$key] ?? 0) + (int) $item->quantity;
        }

        $fastMoving = $products->map(function ($product) use ($soldUnitsByProduct): array {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category_name ?: 'Uncategorized',
                'stock' => (int) $product->stock_quantity,
                'units_sold' => (int) ($soldUnitsByProduct[$product->id] ?? 0),
            ];
        })->sortByDesc('units_sold')->values();

        $slowMoving = $fastMoving->filter(fn ($row) => $row['stock'] > 0)
            ->sortBy(fn ($row) => [$row['units_sold'], -$row['stock']])
            ->values();

        $purchases = DB::table('stock_purchases')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_purchases.supplier_id')
            ->where('stock_purchases.status', '!=', 'cancelled')
            ->whereBetween('stock_purchases.purchase_date', [$from->toDateString(), $to->toDateString()])
            ->select('stock_purchases.*', DB::raw('COALESCE(suppliers.name, stock_purchases.supplier_name) as supplier_display'))
            ->get();

        $supplierBalances = DB::table('suppliers')
            ->where('is_active', true)
            ->get(['id', 'name', 'opening_balance'])
            ->map(function ($supplier): array {
                $purchases = DB::table('stock_purchases')->where('supplier_id', $supplier->id)->where('status', '!=', 'cancelled')->get(['id', 'total']);
                $ids = $purchases->pluck('id');
                $paid = $ids->isEmpty() ? 0 : (float) DB::table('stock_purchase_payments')->whereIn('stock_purchase_id', $ids)->sum('amount');
                $credits = $ids->isEmpty() ? 0 : (float) DB::table('stock_purchase_returns')->whereIn('stock_purchase_id', $ids)->sum('credit_amount');
                $balance = (float) $supplier->opening_balance + (float) $purchases->sum('total') - $paid - $credits;
                return ['id' => $supplier->id, 'name' => $supplier->name, 'balance' => max(0, $balance)];
            })
            ->sortByDesc('balance')->values();

        $posReturns = DB::table('pos_sale_returns')
            ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'refund_amount', 'return_date']);
        $returnIds = $posReturns->pluck('id');
        $returnItems = $returnIds->isEmpty() ? collect() : DB::table('pos_sale_return_items')
            ->join('pos_sale_items', 'pos_sale_items.id', '=', 'pos_sale_return_items.pos_sale_item_id')
            ->whereIn('pos_sale_return_items.pos_sale_return_id', $returnIds)
            ->get(['pos_sale_return_items.quantity', 'pos_sale_return_items.restock', 'pos_sale_items.unit_cost']);

        $posCogs = (float) $posItems->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost)
            - (float) $returnItems->where('restock', true)->sum(fn ($line) => (int) $line->quantity * (float) $line->unit_cost);
        $onlineCogs = (float) $onlineItems->sum(fn ($line) => (int) $line->quantity * (float) ($line->unit_cost ?? 0));
        $revenue = (float) $posSales->sum('total') - (float) $posReturns->sum('refund_amount') + (float) $onlineOrders->sum('grand_total');
        $cogs = $posCogs + $onlineCogs;
        $grossProfit = $revenue - $cogs;
        $expenses = (float) DB::table('expenses')->where('status', 'posted')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->sum('amount');

        $daily = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();
            $posDay = $posSales->filter(fn ($row) => Carbon::parse($row->created_at)->toDateString() === $date)->sum('total');
            $onlineDay = $onlineOrders->filter(fn ($row) => Carbon::parse($row->created_at)->toDateString() === $date)->sum('grand_total');
            $refundDay = $posReturns->where('return_date', $date)->sum('refund_amount');
            $daily->push(['date' => $date, 'sales' => (float) $posDay + (float) $onlineDay - (float) $refundDay]);
            $cursor->addDay();
        }

        $paymentMethods = $posSales->groupBy('payment_method')->map(fn (Collection $rows, $method) => [
            'method' => $method,
            'count' => $rows->count(),
            'amount' => (float) $rows->sum('total'),
        ])->sortByDesc('amount')->values();

        $cashiers = $posSales->groupBy('created_by')->map(function (Collection $rows, $userId): array {
            $name = $userId ? DB::table('users')->where('id', $userId)->value('name') : null;
            return ['cashier' => $name ?: 'Unassigned / legacy', 'count' => $rows->count(), 'amount' => (float) $rows->sum('total')];
        })->sortByDesc('amount')->values();

        return Inertia::render('Admin/Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'inventory_value' => $inventoryValue,
                'stock_units' => (int) $products->where('track_stock', true)->sum('stock_quantity'),
                'low_stock' => $products->filter(fn ($p) => $p->track_stock && $p->stock_quantity > 0 && $p->stock_quantity <= $p->low_stock_threshold)->count(),
                'out_of_stock' => $products->filter(fn ($p) => $p->track_stock && $p->stock_quantity <= 0)->count(),
                'purchases' => (float) $purchases->sum('total'),
                'supplier_balance' => (float) $supplierBalances->sum('balance'),
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'expenses' => $expenses,
                'net_result' => $grossProfit - $expenses,
                'refunds' => (float) $posReturns->sum('refund_amount'),
            ],
            'inventory' => [
                'lowStock' => $products->filter(fn ($p) => $p->track_stock && $p->stock_quantity > 0 && $p->stock_quantity <= $p->low_stock_threshold)->values()->take(30),
                'outOfStock' => $products->filter(fn ($p) => $p->track_stock && $p->stock_quantity <= 0)->values()->take(30),
                'fastMoving' => $fastMoving->take(20),
                'slowMoving' => $slowMoving->take(20),
                'adjustments' => $movementRows->filter(fn ($m) => str_starts_with((string) $m->type, 'adjustment') || in_array($m->type, ['opening_balance', 'reversal'], true))->values()->take(50),
            ],
            'purchases' => [
                'recent' => $purchases->sortByDesc('purchase_date')->values()->take(50),
                'bySupplier' => $purchases->groupBy('supplier_display')->map(fn (Collection $rows, $supplier) => ['supplier' => $supplier ?: 'One-time supplier', 'count' => $rows->count(), 'amount' => (float) $rows->sum('total')])->sortByDesc('amount')->values(),
                'supplierBalances' => $supplierBalances->take(50),
            ],
            'sales' => [
                'daily' => $daily,
                'paymentMethods' => $paymentMethods,
                'cashiers' => $cashiers,
                'posCount' => $posSales->count(),
                'onlineCount' => $onlineOrders->count(),
            ],
        ]);
    }
}
