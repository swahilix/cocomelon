<?php

use App\Jobs\SystemQueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    Cache::put('system-monitor.scheduler-heartbeat', now()->toIso8601String(), now()->addHours(2));
    SystemQueueHeartbeat::dispatch();
})->everyMinute()->name('system-monitor-heartbeat')->withoutOverlapping();

Schedule::command('merchant:sync --stale')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
