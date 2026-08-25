<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class CustomerOtpSmsService
{
    private const NEXTSMS_URL = 'https://messaging-service.co.tz/api/sms/v1/text/single';

    private const MOMO_URL = 'https://business.momo.tz/api/v3/sms/send';

    public function send(string $phone, string $code): void
    {
        $this->sendMessage($phone, "Your Prinx verification code is {$code}. It expires in 5 minutes. Do not share this code.");
    }

    public function sendMessage(string $phone, string $message): void
    {
        if (app()->environment('testing') && ! config('services.customer_sms.force_http', false)) {
            Log::info('Customer SMS suppressed in testing.', ['phone' => $phone, 'message' => $message]);
            return;
        }

        $settings = $this->settings();

        if (! $settings || ! (bool) ($settings->sms_enabled ?? false)) {
            throw new RuntimeException('SMS is not configured yet. Please enable a provider in Business Settings.');
        }

        $provider = strtolower(trim((string) ($settings->sms_provider ?? 'nextsms')));

        if ($provider === 'nextsms') {
            $this->sendWithNextSms($settings, $phone, $message);
            return;
        }

        if ($provider === 'momo') {
            $this->sendWithMomo($settings, $phone, $message);
            return;
        }

        throw new RuntimeException('Choose Next SMS or Momo SMS in Business Settings and save the settings again.');
    }

    private function sendWithNextSms(object $settings, string $phone, string $message): void
    {
        $apiKey = $this->secret($settings->sms_api_key ?? null);
        $sender = trim((string) ($settings->sms_sender_id ?? ''));
        $recipient = $this->normalizeTanzanianNumber($phone);

        if (! $apiKey || $sender === '') {
            throw new RuntimeException('Next SMS is not fully configured. Save the API key and sender ID in Business Settings.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->withHeaders(['Authorization' => 'Basic '.$apiKey])
                ->post(self::NEXTSMS_URL, [
                    'from' => $sender,
                    'to' => $recipient,
                    'text' => $message,
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not connect to Next SMS. Please try again shortly.', previous: $exception);
        }

        if (! $response->successful()) {
            Log::warning('Next SMS message failed.', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);
            throw new RuntimeException('Next SMS could not send the message. Please try again.');
        }
    }

    private function sendWithMomo(object $settings, string $phone, string $message): void
    {
        $token = $this->secret($settings->sms_bearer_token ?? null);
        $sender = trim((string) ($settings->sms_sender_id ?? ''));
        $recipient = $this->normalizeTanzanianNumber($phone);

        if (! $token) {
            throw new RuntimeException('Momo SMS is not fully configured. Save the API token in Business Settings.');
        }

        $payload = [
            'recipient' => $recipient,
            'message' => $message,
        ];

        if ($sender !== '') {
            $payload['sender_id'] = $sender;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->withToken($token)
                ->post(self::MOMO_URL, $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not connect to Momo SMS. Please try again shortly.', previous: $exception);
        }

        if (! $response->successful()) {
            Log::warning('Momo SMS message failed.', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);
            throw new RuntimeException('Momo SMS could not send the message. Please try again.');
        }
    }

    private function normalizeTanzanianNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', trim($number)) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        if (! preg_match('/^255\d{9}$/', $digits)) {
            throw new RuntimeException('Enter a valid Tanzanian mobile number.');
        }

        return $digits;
    }

    private function settings(): ?object
    {
        if (! Schema::hasTable('business_settings') || ! Schema::hasColumn('business_settings', 'sms_enabled')) {
            return null;
        }

        return DB::table('business_settings')->where('id', 1)->first();
    }

    private function secret(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
}
