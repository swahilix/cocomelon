<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Composes, sends and logs the customer SMS messages for a warranty:
 *  - thank_you: sent when the warranty is saved
 *  - details:   sent right after, with start and end dates
 *  - expired:   sent by the daily scheduler once the warranty has ended
 */
class WarrantySmsService
{
    public const TYPES = ['thank_you', 'details', 'expired'];

    public function __construct(private readonly CustomerOtpSmsService $sms)
    {
    }

    /**
     * Send a message of the given type and record the outcome.
     * Returns null on success or the error text on failure.
     */
    public function send(object $warranty, string $type): ?string
    {
        $message = $this->compose($warranty, $type);
        $now = now();

        $logId = DB::table('warranty_messages')->insertGetId([
            'warranty_id' => $warranty->id,
            'type' => $type,
            'phone' => $warranty->customer_phone,
            'message' => $message,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $this->sms->sendMessage((string) $warranty->customer_phone, $message);
        } catch (Throwable $exception) {
            Log::warning('Warranty SMS failed.', [
                'warranty' => $warranty->reference,
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);

            DB::table('warranty_messages')->where('id', $logId)->update([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 1000),
                'updated_at' => now(),
            ]);

            return $exception->getMessage();
        }

        DB::table('warranty_messages')->where('id', $logId)->update([
            'status' => 'sent',
            'error' => null,
            'sent_at' => now(),
            'updated_at' => now(),
        ]);

        if ($type === 'expired') {
            DB::table('warranties')->where('id', $warranty->id)->update([
                'expiry_notified_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return null;
    }

    public function compose(object $warranty, string $type): string
    {
        $business = $this->businessName();
        $swahili = ($warranty->sms_language ?? 'en') === 'sw';
        $name = trim(Str::before(trim((string) $warranty->customer_name), ' ')) ?: trim((string) $warranty->customer_name);
        $product = Str::limit(trim((string) $warranty->product_name), 60, '…');
        $starts = CarbonImmutable::parse($warranty->starts_on);
        $ends = CarbonImmutable::parse($warranty->ends_on);
        $date = fn (CarbonImmutable $value): string => $swahili ? $value->format('d/m/Y') : $value->format('d M Y');
        $duration = $this->durationLabel((int) $warranty->duration_value, (string) $warranty->duration_unit, $swahili);
        $serial = filled($warranty->serial_number ?? null) ? ' (S/N '.$warranty->serial_number.')' : '';

        return match ($type) {
            'thank_you' => $swahili
                ? "Asante {$name} kwa kununua bidhaa kutoka {$business}. Tunathamini imani yako na tunatarajia kukuhudumia tena."
                : "Thank you {$name} for shopping with {$business}. We appreciate your trust and look forward to serving you again.",
            'details' => $swahili
                ? "{$business}: Dhamana {$warranty->reference} ya {$product}{$serial} imeanza tarehe {$date($starts)} na itaisha tarehe {$date($ends)} ({$duration}). Hifadhi ujumbe huu kwa ajili ya madai ya dhamana."
                : "{$business}: Warranty {$warranty->reference} for {$product}{$serial} has been initiated on {$date($starts)} and will end on {$date($ends)} ({$duration}). Keep this message for warranty claims.",
            'expired' => $swahili
                ? "{$business}: Dhamana {$warranty->reference} ya {$product}{$serial} imeisha tarehe {$date($ends)}. Asante kwa kuchagua {$business}. Wasiliana nasi kwa matengenezo au bidhaa mpya."
                : "{$business}: Your warranty {$warranty->reference} for {$product}{$serial} ended on {$date($ends)}. Thank you for choosing {$business}. Contact us for repairs or upgrades.",
            default => throw new InvalidArgumentException("Unknown warranty message type [{$type}]."),
        };
    }

    public function durationLabel(int $value, string $unit, bool $swahili = false): string
    {
        if ($swahili) {
            return match ($unit) {
                'days' => $value === 1 ? 'siku 1' : "siku {$value}",
                'years' => $value === 1 ? 'mwaka 1' : "miaka {$value}",
                default => $value === 1 ? 'mwezi 1' : "miezi {$value}",
            };
        }

        $singular = match ($unit) {
            'days' => 'day',
            'years' => 'year',
            default => 'month',
        };

        return $value.' '.($value === 1 ? $singular : $singular.'s');
    }

    private function businessName(): string
    {
        $name = null;

        if (Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'business_name')) {
            $name = DB::table('business_settings')->where('id', 1)->value('business_name');
        }

        return trim((string) ($name ?: config('app.name', 'Prinx Tech'))) ?: 'Prinx Tech';
    }
}
