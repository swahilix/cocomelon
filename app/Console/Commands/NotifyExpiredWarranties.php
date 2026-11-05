<?php

namespace App\Console\Commands;

use App\Services\WarrantySmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyExpiredWarranties extends Command
{
    protected $signature = 'warranties:notify-expired {--max-attempts=3 : Stop retrying a failed expiry SMS after this many attempts}';

    protected $description = 'Mark ended warranties as expired and send each customer a warranty-ended SMS';

    public function handle(WarrantySmsService $sms): int
    {
        $today = now()->toDateString();
        $maxAttempts = max(1, (int) $this->option('max-attempts'));

        $marked = DB::table('warranties')
            ->where('status', 'active')
            ->where('ends_on', '<', $today)
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $sent = 0;
        $failed = 0;

        DB::table('warranties')
            ->where('status', 'expired')
            ->whereNull('expiry_notified_at')
            ->where('ends_on', '<', $today)
            ->orderBy('id')
            ->chunkById(100, function ($warranties) use ($sms, $maxAttempts, &$sent, &$failed): void {
                foreach ($warranties as $warranty) {
                    $attempts = DB::table('warranty_messages')
                        ->where('warranty_id', $warranty->id)
                        ->where('type', 'expired')
                        ->count();

                    if ($attempts >= $maxAttempts) {
                        continue;
                    }

                    $sms->send($warranty, 'expired') === null ? $sent++ : $failed++;
                }
            });

        $this->info("Marked {$marked} warranties expired. Expiry SMS sent: {$sent}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
