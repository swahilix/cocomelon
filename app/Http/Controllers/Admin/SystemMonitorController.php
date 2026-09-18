<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SystemMonitorController extends Controller
{
    public function __invoke(): Response
    {
        $schedulerHeartbeat = $this->heartbeat('system-monitor.scheduler-heartbeat');
        $queueHeartbeat = $this->heartbeat('system-monitor.queue-heartbeat');

        return Inertia::render('Admin/SystemMonitor', [
            'scheduler' => $this->status($schedulerHeartbeat),
            'queue' => $this->status($queueHeartbeat),
            'queueConnection' => config('queue.default'),
            'counts' => [
                'pending' => $this->tableCount('jobs'),
                'failed' => $this->tableCount('failed_jobs'),
            ],
            'serverTime' => now()->toIso8601String(),
            'launchChecks' => $this->launchChecks(),
            'commands' => [
                'cron' => '* * * * * php '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
                'queue' => 'php '.base_path('artisan').' queue:work --sleep=3 --tries=3 --timeout=90',
            ],
        ]);
    }

    private function heartbeat(string $key): ?CarbonImmutable
    {
        $value = Cache::get($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function status(?CarbonImmutable $heartbeat): array
    {
        if (! $heartbeat) {
            return ['running' => false, 'last_seen' => null, 'age_seconds' => null];
        }

        $age = max(0, $heartbeat->diffInSeconds(now()));

        return [
            'running' => $age <= 180,
            'last_seen' => $heartbeat->toIso8601String(),
            'age_seconds' => $age,
        ];
    }

    private function tableCount(string $table): ?int
    {
        try {
            if (! Schema::hasTable($table)) {
                return null;
            }

            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function launchChecks(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $businessTable = Schema::hasTable('business_settings');
        $business = $businessTable ? DB::table('business_settings')->where('id', 1)->first() : null;

        $contactReady = $business
            && Schema::hasColumn('business_settings', 'phone')
            && Schema::hasColumn('business_settings', 'whatsapp')
            && Schema::hasColumn('business_settings', 'address')
            && filled($business->phone ?? null)
            && filled($business->whatsapp ?? null)
            && filled($business->address ?? null);

        $conversionReady = Schema::hasTable('conversion_events')
            && Schema::hasColumn('orders', 'acquisition_source')
            && Schema::hasColumn('orders', 'attribution_session_key');

        $discoveryReady = Route::has('sitemap')
            && Route::has('feeds.merchant')
            && Route::has('local.store');

        $activeProducts = Schema::hasTable('products')
            ? (int) DB::table('products')->where('status', 'active')->count()
            : 0;

        return [
            [
                'key' => 'https',
                'label' => 'HTTPS storefront',
                'ok' => str_starts_with($appUrl, 'https://'),
                'detail' => str_starts_with($appUrl, 'https://') ? $appUrl : 'APP_URL is not using HTTPS.',
            ],
            [
                'key' => 'discovery',
                'label' => 'Search discovery routes',
                'ok' => $discoveryReady,
                'detail' => $discoveryReady ? 'Sitemap, Merchant feed and Dodoma landing route are registered.' : 'One or more public discovery routes are missing.',
            ],
            [
                'key' => 'conversion',
                'label' => 'Conversion measurement',
                'ok' => $conversionReady,
                'detail' => $conversionReady ? 'First-party conversion table and order attribution columns are ready.' : 'Conversion tracking schema is incomplete.',
            ],
            [
                'key' => 'contact',
                'label' => 'Business contact data',
                'ok' => (bool) $contactReady,
                'detail' => $contactReady ? 'Phone, WhatsApp and address are configured.' : 'Complete phone, WhatsApp and address in Business Settings.',
            ],
            [
                'key' => 'catalog',
                'label' => 'Active storefront products',
                'ok' => $activeProducts > 0,
                'detail' => $activeProducts > 0 ? $activeProducts.' active products available.' : 'No active products are available.',
            ],
        ];
    }
}
