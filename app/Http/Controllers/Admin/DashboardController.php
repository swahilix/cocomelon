<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MerchantSyncLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceInquiry;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'products' => Product::count(),
                'active_products' => Product::active()->count(),
                'low_stock_products' => Product::where('track_stock', true)->where('stock_quantity','>',0)->whereColumn('stock_quantity','<=','low_stock_threshold')->count(),
                'out_of_stock_products' => Product::where('track_stock', true)->where('stock_quantity',0)->count(),
                'deleted_products' => Product::onlyTrashed()->count(),
                'services' => Service::count(),
                'deleted_services' => Service::onlyTrashed()->count(),
                'categories' => Category::count(),
                'pending_orders' => Order::whereIn('status', ['pending', 'confirmed', 'processing'])->count(),
                'new_inquiries' => ServiceInquiry::where('status', 'new')->count(),
                'failed_syncs' => Product::where('merchant_sync_status', 'failed')->count(),
                'revenue' => Order::where('payment_status', 'paid')->sum('grand_total'),
            ],
            'recentOrders' => Order::latest()->take(6)->get(),
            'recentSyncs' => MerchantSyncLog::with('product:id,name,slug')->latest()->take(6)->get(),
        ]);
    }
}
