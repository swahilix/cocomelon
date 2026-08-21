<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
}
