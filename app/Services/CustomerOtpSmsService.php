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
    public function send(string $phone, string $code): void
    {
        $message = "Your Prinx verification code is {$code}. It expires in 5 minutes. Do not share this code.";

        if (app()->environment('testing')) {
            Log::info('Customer OTP SMS suppressed in testing.', ['phone' => $phone]);
            return;
        }

        $settings = $this->settings();

        if (! $settings || ! (bool) ($settings->sms_enabled ?? false)) {
            throw new RuntimeException('SMS login is not configured yet. Please contact Prinx support.');
        }

        $provider = (string) ($settings->sms_provider ?? 'beem');

        if ($provider === 'beem') {
            $this->sendWithBeem($settings, $phone, $message);
            return;
        }

        if ($provider === 'custom') {
            $this->sendWithCustomWebhook($settings, $phone, $message);
            return;
        }

        throw new RuntimeException('The configured SMS provider is not supported.');
    }

    private function sendWithBeem(object $settings, string $phone, string $message): void
    {
        $key = $this->secret($settings->sms_api_key ?? null);
        $secret = $this->secret($settings->sms_api_secret ?? null);
        $sender = trim((string) ($settings->sms_sender_id ?? ''));
        $url = trim((string) ($settings->sms_api_url ?? '')) ?: 'https://apisms.beem.africa/v1/send';

        if (! $key || ! $secret || ! $sender) {
            throw new RuntimeException('Beem SMS credentials are incomplete in Business Settings.');
        }

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->withBasicAuth($key, $secret)
                ->post($url, [
                    'source_addr' => $sender,
                    'encoding' => 0,
                    'schedule_time' => '',
                    'message' => $message,
                    'recipients' => [[
                        'recipient_id' => 1,
                        'dest_addr' => ltrim($phone, '+'),
                    ]],
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not connect to the SMS provider. Please try again shortly.', previous: $exception);
        }

        if (! $response->successful()) {
            Log::warning('Beem OTP SMS failed.', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('The SMS provider could not send your verification code. Please try again.');
        }
    }

    private function sendWithCustomWebhook(object $settings, string $phone, string $message): void
    {
        $url = trim((string) ($settings->sms_api_url ?? ''));
        $sender = trim((string) ($settings->sms_sender_id ?? ''));
        $token = $this->secret($settings->sms_bearer_token ?? null);

        if (! $url) {
            throw new RuntimeException('Custom SMS webhook URL is missing in Business Settings.');
        }

        $request = Http::timeout(12)->acceptJson();
        if ($token) {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post($url, [
                'to' => $phone,
                'message' => $message,
                'sender_id' => $sender ?: null,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not connect to the SMS provider. Please try again shortly.', previous: $exception);
        }

        if (! $response->successful()) {
            Log::warning('Custom OTP SMS failed.', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('The SMS provider could not send your verification code. Please try again.');
        }
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
