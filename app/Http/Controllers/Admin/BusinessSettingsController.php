<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageOptimizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BusinessSettingsController extends Controller
{
    public function __construct(private readonly ImageOptimizationService $images)
    {
    }

    public function edit(): Response
    {
        $settings = DB::table('business_settings')->where('id', 1)->first();
        $paymentStrip = Schema::hasColumn('business_settings', 'payment_strip')
            ? $settings?->payment_strip
            : null;
        $smsReady = Schema::hasColumn('business_settings', 'sms_enabled');

        $safeSettings = (array) ($settings ?? []);
        foreach (['sms_api_key', 'sms_api_secret', 'sms_bearer_token'] as $secret) {
            unset($safeSettings[$secret]);
        }

        if ($smsReady) {
            $safeSettings += [
                'sms_enabled' => false,
                'sms_provider' => 'beem',
                'sms_api_url' => null,
                'sms_sender_id' => null,
            ];
        }

        return Inertia::render('Admin/BusinessSettings/Edit', [
            'settings' => $safeSettings,
            'paymentStripUrl' => $this->mediaUrl($paymentStrip),
            'smsAvailable' => $smsReady,
            'smsSecrets' => [
                'api_key' => $smsReady && filled($settings?->sms_api_key ?? null),
                'api_secret' => $smsReady && filled($settings?->sms_api_secret ?? null),
                'bearer_token' => $smsReady && filled($settings?->sms_bearer_token ?? null),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'secondary_phone' => ['nullable', 'string', 'max:80'],
            'whatsapp' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'tiktok_url' => ['nullable', 'url:http,https', 'max:255'],
            'youtube_url' => ['nullable', 'url:http,https', 'max:255'],
            'x_url' => ['nullable', 'url:http,https', 'max:255'],
            'maps_url' => ['nullable', 'url:http,https', 'max:255'],
            'payment_strip' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'sms_enabled' => ['nullable', 'boolean'],
            'sms_provider' => ['nullable', Rule::in(['beem', 'custom'])],
            'sms_api_url' => ['nullable', 'url:http,https', 'max:255'],
            'sms_api_key' => ['nullable', 'string', 'max:1000'],
            'sms_api_secret' => ['nullable', 'string', 'max:1000'],
            'sms_sender_id' => ['nullable', 'string', 'max:40'],
            'sms_bearer_token' => ['nullable', 'string', 'max:2000'],
        ]);

        $current = DB::table('business_settings')->where('id', 1)->first();
        $currentPaymentStrip = Schema::hasColumn('business_settings', 'payment_strip')
            ? $current?->payment_strip
            : null;
        $smsFieldsSubmitted = $request->hasAny(['sms_enabled', 'sms_provider', 'sms_api_url', 'sms_api_key', 'sms_api_secret', 'sms_sender_id', 'sms_bearer_token']);

        if ($smsFieldsSubmitted && ! Schema::hasColumn('business_settings', 'sms_enabled')) {
            throw ValidationException::withMessages([
                'sms_enabled' => 'Run the latest database migrations before configuring customer SMS login.',
            ]);
        }

        if (Schema::hasColumn('business_settings', 'sms_enabled')) {
            $enabled = (bool) ($validated['sms_enabled'] ?? false);
            $provider = (string) ($validated['sms_provider'] ?? $current?->sms_provider ?? 'beem');
            $hasApiKey = filled($validated['sms_api_key'] ?? null) || filled($current?->sms_api_key ?? null);
            $hasApiSecret = filled($validated['sms_api_secret'] ?? null) || filled($current?->sms_api_secret ?? null);
            $sender = trim((string) ($validated['sms_sender_id'] ?? $current?->sms_sender_id ?? ''));
            $url = trim((string) ($validated['sms_api_url'] ?? $current?->sms_api_url ?? ''));

            if ($enabled && $provider === 'beem' && (! $hasApiKey || ! $hasApiSecret || $sender === '')) {
                throw ValidationException::withMessages([
                    'sms_enabled' => 'To enable Beem SMS, provide an API key, API secret and sender ID.',
                ]);
            }

            if ($enabled && $provider === 'custom' && $url === '') {
                throw ValidationException::withMessages([
                    'sms_api_url' => 'A custom SMS webhook URL is required when customer SMS login is enabled.',
                ]);
            }

            foreach (['sms_api_key', 'sms_api_secret', 'sms_bearer_token'] as $secretField) {
                if (filled($validated[$secretField] ?? null)) {
                    $validated[$secretField] = Crypt::encryptString(trim((string) $validated[$secretField]));
                } else {
                    unset($validated[$secretField]);
                }
            }
        } else {
            foreach (['sms_enabled', 'sms_provider', 'sms_api_url', 'sms_api_key', 'sms_api_secret', 'sms_sender_id', 'sms_bearer_token'] as $field) {
                unset($validated[$field]);
            }
        }

        if ($request->hasFile('payment_strip')) {
            if (! Schema::hasColumn('business_settings', 'payment_strip')) {
                throw ValidationException::withMessages([
                    'payment_strip' => 'Run the latest database migrations before uploading the payment strip.',
                ]);
            }

            try {
                $validated['payment_strip'] = $this->images->store(
                    $request->file('payment_strip'),
                    'business',
                    'accepted payment methods',
                    [
                        'title' => 'Accepted payment methods | Prinx Electronics',
                        'description' => 'Payment methods accepted by Prinx Electronics.',
                        'keywords' => ['payment methods', 'Prinx Electronics', 'Tanzania'],
                    ]
                );
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    'payment_strip' => $exception->getMessage(),
                ]);
            }

            $this->images->deleteLocal($currentPaymentStrip);
        } else {
            unset($validated['payment_strip']);
        }

        $payload = collect($validated)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
        $payload['updated_at'] = now();

        if (DB::table('business_settings')->where('id', 1)->exists()) {
            DB::table('business_settings')->where('id', 1)->update($payload);
        } else {
            DB::table('business_settings')->insert([
                'id' => 1,
                ...$payload,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Business settings updated successfully.');
    }

    private function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk(config('media.disk', 'public'))->url($path);
    }
}
