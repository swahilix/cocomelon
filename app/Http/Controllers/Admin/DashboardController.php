<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MerchantSyncLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        $user = request()->user();
        if (! $user?->hasPermission('manage_storefront')) {
            if ($user?->hasPermission('use_pos')) return redirect()->route('admin.pos.index');
            if ($user?->hasPermission('view_inventory')) return redirect()->route('admin.stock.inventory');
            if ($user?->hasPermission('view_profit_reports')) return redirect()->route('admin.profit.index');
            if ($user?->hasPermission('manage_expenses')) return redirect()->route('admin.expenses.index');
            if ($user?->hasPermission('view_reports')) return redirect()->route('admin.reports.index');
            abort(403, 'No admin workspace is available for your current role.');
        }

        $today = now()->toDateString();

        $posSales = DB::table('pos_sales')->where('status', '!=', 'voided')->whereDate('created_at', $today)->get(['id', 'total']);
        $posIds = $posSales->pluck('id');
        $posCogs = $posIds->isEmpty() ? 0 : (float) DB::table('pos_sale_items')->whereIn('pos_sale_id', $posIds)->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')->value('total');
        $posRefunds = (float) DB::table('pos_sale_returns')->whereDate('return_date', $today)->sum('refund_amount');
        $restockedReturnCogs = (float) DB::table('pos_sale_return_items')
            ->join('pos_sale_returns', 'pos_sale_returns.id', '=', 'pos_sale_return_items.pos_sale_return_id')
            ->join('pos_sale_items', 'pos_sale_items.id', '=', 'pos_sale_return_items.pos_sale_item_id')
            ->whereDate('pos_sale_returns.return_date', $today)
            ->where('pos_sale_return_items.restock', true)
            ->selectRaw('COALESCE(SUM(pos_sale_return_items.quantity * pos_sale_items.unit_cost), 0) as total')
            ->value('total');

        $onlineOrders = Order::query()
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled')
            ->whereNotIn('payment_status', ['failed', 'refunded'])
            ->where(function ($q): void {
                $q->where('status', 'completed')->orWhere('payment_status', 'paid');
            })
            ->get(['id', 'grand_total']);
        $onlineIds = $onlineOrders->pluck('id');
        $onlineCogs = $onlineIds->isEmpty() ? 0 : (float) DB::table('order_items')->whereIn('order_id', $onlineIds)->selectRaw('COALESCE(SUM(quantity * COALESCE(unit_cost, 0)), 0) as total')->value('total');

        $todayPosRevenue = (float) $posSales->sum('total') - $posRefunds;
        $todayOnlineRevenue = (float) $onlineOrders->sum('grand_total');
        $todayRevenue = $todayPosRevenue + $todayOnlineRevenue;
        $todayCogs = $posCogs - $restockedReturnCogs + $onlineCogs;
        $todayGrossProfit = $todayRevenue - $todayCogs;
        $todayExpenses = (float) DB::table('expenses')->where('status', 'posted')->whereDate('expense_date', $today)->sum('amount');

        $supplierBalance = DB::table('suppliers')->where('is_active', true)->get(['id', 'opening_balance'])->sum(function ($supplier): float {
            $purchases = DB::table('stock_purchases')->where('supplier_id', $supplier->id)->where('status', '!=', 'cancelled')->get(['id', 'total']);
            $ids = $purchases->pluck('id');
            $paid = $ids->isEmpty() ? 0 : (float) DB::table('stock_purchase_payments')->whereIn('stock_purchase_id', $ids)->sum('amount');
            $credits = $ids->isEmpty() ? 0 : (float) DB::table('stock_purchase_returns')->whereIn('stock_purchase_id', $ids)->sum('credit_amount');
            return max(0, (float) $supplier->opening_balance + (float) $purchases->sum('total') - $paid - $credits);
        });

        $inventoryValue = Product::query()->where('track_stock', true)->get(['stock_quantity', 'average_cost'])
            ->sum(fn (Product $product) => max(0, (int) $product->stock_quantity) * (float) $product->average_cost);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'products' => Product::count(),
                'active_products' => Product::active()->count(),
                'low_stock_products' => Product::where('track_stock', true)->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
                'out_of_stock_products' => Product::where('track_stock', true)->where('stock_quantity', '<=', 0)->count(),
                'deleted_products' => Product::onlyTrashed()->count(),
                'services' => Service::count(),
                'deleted_services' => Service::onlyTrashed()->count(),
                'categories' => Category::count(),
                'pending_orders' => Order::whereIn('status', ['pending', 'confirmed', 'processing'])->count(),
                'new_inquiries' => ServiceInquiry::where('status', 'new')->count(),
                'failed_syncs' => Product::where('merchant_sync_status', 'failed')->count(),
                'revenue' => Order::where('payment_status', 'paid')->sum('grand_total'),
                'today_pos_revenue' => $todayPosRevenue,
                'today_online_revenue' => $todayOnlineRevenue,
                'today_sales' => $todayRevenue,
                'today_gross_profit' => $todayGrossProfit,
                'today_expenses' => $todayExpenses,
                'today_net_result' => $todayGrossProfit - $todayExpenses,
                'today_purchases' => (float) DB::table('stock_purchases')->where('status', '!=', 'cancelled')->whereDate('purchase_date', $today)->sum('total'),
                'inventory_value' => $inventoryValue,
                'supplier_balance' => $supplierBalance,
            ],
            'recentOrders' => Order::latest()->take(6)->get(),
            'recentSyncs' => MerchantSyncLog::with('product:id,name,slug')->latest()->take(6)->get(),
        ]);
    }
}
