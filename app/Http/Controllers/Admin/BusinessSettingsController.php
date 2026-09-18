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
            $provider = strtolower((string) ($settings?->sms_provider ?? 'nextsms'));
            if (! in_array($provider, ['nextsms', 'momo'], true)) {
                $provider = 'nextsms';
            }

            $safeSettings['sms_enabled'] = (bool) ($settings?->sms_enabled ?? false);
            $safeSettings['sms_provider'] = $provider;
            $safeSettings['sms_sender_id'] = $settings?->sms_sender_id ?? null;
        }

        return Inertia::render('Admin/BusinessSettings/Edit', [
            'settings' => $safeSettings,
            'paymentStripUrl' => $this->mediaUrl($paymentStrip),
            'smsAvailable' => $smsReady,
            'smsSecrets' => [
                'nextsms_api_key' => $smsReady && filled($settings?->sms_api_key ?? null),
                'momo_api_token' => $smsReady && filled($settings?->sms_bearer_token ?? null),
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
            'delivery_enabled' => ['nullable', 'boolean'],
            'delivery_area' => ['nullable', 'string', 'max:255'],
            'delivery_eta' => ['nullable', 'string', 'max:120'],
            'pickup_enabled' => ['nullable', 'boolean'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'pickup_instructions' => ['nullable', 'string', 'max:255'],
            'payment_mobile_money_enabled' => ['nullable', 'boolean'],
            'payment_bank_transfer_enabled' => ['nullable', 'boolean'],
            'payment_cash_on_delivery_enabled' => ['nullable', 'boolean'],
            'payment_mobile_money_description' => ['nullable', 'string', 'max:255'],
            'payment_bank_transfer_description' => ['nullable', 'string', 'max:255'],
            'payment_cash_on_delivery_description' => ['nullable', 'string', 'max:255'],
            'payment_safety_note' => ['nullable', 'string', 'max:255'],
            'payment_strip_visible' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'tiktok_url' => ['nullable', 'url:http,https', 'max:255'],
            'youtube_url' => ['nullable', 'url:http,https', 'max:255'],
            'x_url' => ['nullable', 'url:http,https', 'max:255'],
            'maps_url' => ['nullable', 'url:http,https', 'max:255'],
            'payment_strip' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'sms_enabled' => ['nullable', 'boolean'],
            'sms_provider' => ['nullable', Rule::in(['nextsms', 'momo'])],
            'sms_api_key' => ['nullable', 'string', 'max:2000'],
            'sms_sender_id' => ['nullable', 'string', 'max:40'],
            'sms_bearer_token' => ['nullable', 'string', 'max:4000'],
        ]);

        $current = DB::table('business_settings')->where('id', 1)->first();
        $currentPaymentStrip = Schema::hasColumn('business_settings', 'payment_strip')
            ? $current?->payment_strip
            : null;
        $smsFieldsSubmitted = $request->hasAny(['sms_enabled', 'sms_provider', 'sms_api_key', 'sms_sender_id', 'sms_bearer_token']);
        $fulfillmentFields = ['delivery_enabled', 'delivery_area', 'delivery_eta', 'pickup_enabled', 'pickup_location', 'pickup_instructions'];
        $fulfillmentFieldsSubmitted = $request->hasAny($fulfillmentFields);

        if (! Schema::hasColumn('business_settings', 'delivery_enabled')) {
            foreach ($fulfillmentFields as $field) {
                unset($validated[$field]);
            }
        } elseif ($fulfillmentFieldsSubmitted) {
            $deliveryEnabled = (bool) ($validated['delivery_enabled'] ?? false);
            $pickupEnabled = (bool) ($validated['pickup_enabled'] ?? false);

            if (! $deliveryEnabled && ! $pickupEnabled) {
                throw ValidationException::withMessages([
                    'delivery_enabled' => 'Keep at least one fulfillment option enabled: delivery or store pickup.',
                ]);
            }

            if ($deliveryEnabled && blank($validated['delivery_area'] ?? null)) {
                throw ValidationException::withMessages([
                    'delivery_area' => 'Enter the area where local delivery is available.',
                ]);
            }

            if ($deliveryEnabled && blank($validated['delivery_eta'] ?? null)) {
                throw ValidationException::withMessages([
                    'delivery_eta' => 'Enter the delivery estimate customers should see.',
                ]);
            }

            if ($pickupEnabled && blank($validated['pickup_location'] ?? null)) {
                throw ValidationException::withMessages([
                    'pickup_location' => 'Enter the store pickup location.',
                ]);
            }
        }

        $paymentFields = [
            'payment_mobile_money_enabled',
            'payment_bank_transfer_enabled',
            'payment_cash_on_delivery_enabled',
            'payment_mobile_money_description',
            'payment_bank_transfer_description',
            'payment_cash_on_delivery_description',
            'payment_safety_note',
            'payment_strip_visible',
        ];
        $paymentFieldsSubmitted = $request->hasAny($paymentFields);

        if (! Schema::hasColumn('business_settings', 'payment_mobile_money_enabled')) {
            foreach ($paymentFields as $field) {
                unset($validated[$field]);
            }
        } elseif ($paymentFieldsSubmitted) {
            $enabledMethods = collect([
                (bool) ($validated['payment_mobile_money_enabled'] ?? false),
                (bool) ($validated['payment_bank_transfer_enabled'] ?? false),
                (bool) ($validated['payment_cash_on_delivery_enabled'] ?? false),
            ])->filter()->count();

            if ($enabledMethods === 0) {
                throw ValidationException::withMessages([
                    'payment_mobile_money_enabled' => 'Keep at least one customer payment method enabled.',
                ]);
            }
        }

        if ($smsFieldsSubmitted && ! Schema::hasColumn('business_settings', 'sms_enabled')) {
            throw ValidationException::withMessages([
                'sms_enabled' => 'Run the latest database migrations before configuring customer SMS login.',
            ]);
        }

        if (Schema::hasColumn('business_settings', 'sms_enabled')) {
            $enabled = (bool) ($validated['sms_enabled'] ?? false);
            $provider = (string) ($validated['sms_provider'] ?? $current?->sms_provider ?? 'nextsms');
            $sender = trim((string) ($validated['sms_sender_id'] ?? $current?->sms_sender_id ?? ''));
            $hasNextSmsKey = filled($validated['sms_api_key'] ?? null) || filled($current?->sms_api_key ?? null);
            $hasMomoToken = filled($validated['sms_bearer_token'] ?? null) || filled($current?->sms_bearer_token ?? null);

            if ($enabled && $provider === 'nextsms' && (! $hasNextSmsKey || $sender === '')) {
                throw ValidationException::withMessages([
                    'sms_enabled' => 'To enable Next SMS, provide the Basic API key/token and sender ID.',
                ]);
            }

            if ($enabled && $provider === 'momo' && ! $hasMomoToken) {
                throw ValidationException::withMessages([
                    'sms_enabled' => 'To enable Momo SMS, provide the Momo Business API token.',
                ]);
            }

            foreach (['sms_api_key', 'sms_bearer_token'] as $secretField) {
                if (filled($validated[$secretField] ?? null)) {
                    $validated[$secretField] = Crypt::encryptString(trim((string) $validated[$secretField]));
                } else {
                    unset($validated[$secretField]);
                }
            }

            // Legacy Beem/custom fields are intentionally no longer accepted or changed here.
            unset($validated['sms_api_secret'], $validated['sms_api_url']);
        } else {
            foreach (['sms_enabled', 'sms_provider', 'sms_api_key', 'sms_sender_id', 'sms_bearer_token'] as $field) {
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
                        'title' => 'Accepted payment methods | Prinx Tech',
                        'description' => 'Payment methods accepted by Prinx Tech.',
                        'keywords' => ['payment methods', 'Prinx Tech', 'Tanzania'],
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
