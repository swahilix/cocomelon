<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConversionEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversionReportController extends Controller
{
    private const SOURCES = ['google', 'instagram', 'tiktok', 'whatsapp', 'direct', 'referral', 'unknown'];

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/ConversionReport', [
            'days' => $this->days($request),
            ...$this->reportData($this->days($request)),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $days = $this->days($request);
        $report = $this->reportData($days);
        $filename = 'prinx-conversions-'.$days.'d-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($days, $report): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Prinx Tech Conversion Report']);
            fputcsv($out, ['Period', $days.' days']);
            fputcsv($out, ['Generated', now()->toIso8601String()]);
            fputcsv($out, []);

            fputcsv($out, ['Funnel']);
            fputcsv($out, ['Product views', 'Add to cart', 'Buy Now', 'Checkout starts', 'WhatsApp enquiries', 'Completed orders', 'Completed revenue', 'Checkout conversion %']);
            fputcsv($out, [
                $report['summary']['product_views'],
                $report['summary']['cart_adds'],
                $report['summary']['buy_now'],
                $report['summary']['checkout_starts'],
                $report['summary']['whatsapp_enquiries'],
                $report['summary']['completed_orders'],
                $report['summary']['completed_revenue'],
                $report['summary']['checkout_conversion_rate'],
            ]);
            fputcsv($out, []);

            fputcsv($out, ['Source performance']);
            fputcsv($out, ['Source', 'Views', 'Cart adds', 'Buy Now', 'Checkout starts', 'WhatsApp enquiries', 'Completed orders', 'Checkout conversion %', 'Revenue']);
            foreach ($report['sources'] as $source) {
                fputcsv($out, [
                    $source['source'],
                    $source['product_views'],
                    $source['cart_adds'],
                    $source['buy_now'],
                    $source['checkout_starts'],
                    $source['whatsapp_enquiries'],
                    $source['completed_orders'],
                    $source['checkout_conversion_rate'],
                    $source['revenue'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Product interest']);
            fputcsv($out, ['Product', 'Slug', 'Views', 'Cart adds', 'Buy Now', 'WhatsApp enquiries']);
            foreach ($report['topProducts'] as $product) {
                fputcsv($out, [
                    $product->name,
                    $product->slug,
                    $product->product_views,
                    $product->cart_adds,
                    $product->buy_now,
                    $product->whatsapp_enquiries,
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['UTM campaigns']);
            fputcsv($out, ['Campaign', 'Source', 'Events', 'Checkout starts', 'Completed orders', 'Revenue']);
            foreach ($report['campaigns'] as $campaign) {
                fputcsv($out, [
                    $campaign->utm_campaign,
                    $campaign->source,
                    $campaign->events,
                    $campaign->checkout_starts,
                    $campaign->completed_orders,
                    $campaign->revenue,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function days(Request $request): int
    {
        return in_array($request->integer('days'), [7, 30, 90], true) ? $request->integer('days') : 30;
    }

    private function reportData(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        if (! Schema::hasTable('conversion_events')) {
            return [
                'summary' => $this->emptySummary(),
                'sources' => collect(self::SOURCES)->map(fn (string $source) => $this->sourceRow($source))->all(),
                'topProducts' => [],
                'campaigns' => [],
            ];
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

        $sourceRows = collect(self::SOURCES)->map(function (string $source) use ($start): array {
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

        return [
            'summary' => $summary,
            'sources' => $sourceRows,
            'topProducts' => $topProducts,
            'campaigns' => $campaigns,
        ];
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
