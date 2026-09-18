<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConversionEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ConversionReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $days = in_array($request->integer('days'), [7, 30, 90], true) ? $request->integer('days') : 30;
        $start = now()->subDays($days - 1)->startOfDay();
        $sources = ['google', 'instagram', 'tiktok', 'whatsapp', 'direct', 'referral', 'unknown'];

        if (! Schema::hasTable('conversion_events')) {
            return Inertia::render('Admin/ConversionReport', [
                'days' => $days,
                'summary' => $this->emptySummary(),
                'sources' => collect($sources)->map(fn (string $source) => $this->sourceRow($source))->all(),
                'topProducts' => [],
                'campaigns' => [],
            ]);
        }

        $events = ConversionEvent::query()->where('created_at', '>=', $start);
        $counts = (clone $events)
            ->select('event_type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        $completedRevenue = (float) (clone $events)->where('event_type', 'order_completed')->sum('value');
        $completedOrders = (int) ($counts['order_completed'] ?? 0);

        $summary = [
            'product_views' => (int) ($counts['view_product'] ?? 0),
            'cart_adds' => (int) ($counts['add_to_cart'] ?? 0),
            'buy_now' => (int) ($counts['buy_now'] ?? 0),
            'checkout_starts' => (int) ($counts['checkout_start'] ?? 0),
            'whatsapp_enquiries' => (int) ($counts['whatsapp_enquiry'] ?? 0),
            'completed_orders' => $completedOrders,
            'completed_revenue' => $completedRevenue,
            'checkout_conversion_rate' => $this->rate($completedOrders, (int) ($counts['checkout_start'] ?? 0)),
        ];

        $sourceRows = collect($sources)->map(function (string $source) use ($start): array {
            $base = ConversionEvent::query()->where('created_at', '>=', $start)->where('source', $source);
            $counts = (clone $base)
                ->select('event_type')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('event_type')
                ->pluck('total', 'event_type');
            $completed = (int) ($counts['order_completed'] ?? 0);
            $checkout = (int) ($counts['checkout_start'] ?? 0);

            return [
                'source' => $source,
                'product_views' => (int) ($counts['view_product'] ?? 0),
                'cart_adds' => (int) ($counts['add_to_cart'] ?? 0),
                'buy_now' => (int) ($counts['buy_now'] ?? 0),
                'checkout_starts' => $checkout,
                'whatsapp_enquiries' => (int) ($counts['whatsapp_enquiry'] ?? 0),
                'completed_orders' => $completed,
                'revenue' => (float) (clone $base)->where('event_type', 'order_completed')->sum('value'),
                'checkout_conversion_rate' => $this->rate($completed, $checkout),
            ];
        })->all();

        $topProducts = DB::table('conversion_events')
            ->join('products', 'products.id', '=', 'conversion_events.product_id')
            ->where('conversion_events.created_at', '>=', $start)
            ->whereNotNull('conversion_events.product_id')
            ->select('products.id', 'products.name', 'products.slug')
            ->selectRaw("SUM(CASE WHEN conversion_events.event_type = 'view_product' THEN 1 ELSE 0 END) as product_views")
            ->selectRaw("SUM(CASE WHEN conversion_events.event_type = 'add_to_cart' THEN 1 ELSE 0 END) as cart_adds")
            ->selectRaw("SUM(CASE WHEN conversion_events.event_type = 'buy_now' THEN 1 ELSE 0 END) as buy_now")
            ->selectRaw("SUM(CASE WHEN conversion_events.event_type = 'whatsapp_enquiry' THEN 1 ELSE 0 END) as whatsapp_enquiries")
            ->groupBy('products.id', 'products.name', 'products.slug')
            ->orderByDesc('cart_adds')
            ->orderByDesc('product_views')
            ->limit(12)
            ->get();

        $campaigns = DB::table('conversion_events')
            ->where('created_at', '>=', $start)
            ->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->select('utm_campaign', 'source')
            ->selectRaw('COUNT(*) as events')
            ->selectRaw("SUM(CASE WHEN event_type = 'checkout_start' THEN 1 ELSE 0 END) as checkout_starts")
            ->selectRaw("SUM(CASE WHEN event_type = 'order_completed' THEN 1 ELSE 0 END) as completed_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'order_completed' THEN value ELSE 0 END), 0) as revenue")
            ->groupBy('utm_campaign', 'source')
            ->orderByDesc('completed_orders')
            ->orderByDesc('events')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/ConversionReport', [
            'days' => $days,
            'summary' => $summary,
            'sources' => $sourceRows,
            'topProducts' => $topProducts,
            'campaigns' => $campaigns,
        ]);
    }

    private function rate(int $completed, int $started): float
    {
        return $started > 0 ? round(($completed / $started) * 100, 1) : 0.0;
    }

    private function emptySummary(): array
    {
        return [
            'product_views' => 0,
            'cart_adds' => 0,
            'buy_now' => 0,
            'checkout_starts' => 0,
            'whatsapp_enquiries' => 0,
            'completed_orders' => 0,
            'completed_revenue' => 0,
            'checkout_conversion_rate' => 0,
        ];
    }

    private function sourceRow(string $source): array
    {
        return [
            'source' => $source,
            'product_views' => 0,
            'cart_adds' => 0,
            'buy_now' => 0,
            'checkout_starts' => 0,
            'whatsapp_enquiries' => 0,
            'completed_orders' => 0,
            'revenue' => 0,
            'checkout_conversion_rate' => 0,
        ];
    }
}
