<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageOptimizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        return Inertia::render('Admin/BusinessSettings/Edit', [
            'settings' => $settings,
            'paymentStripUrl' => $this->mediaUrl($paymentStrip),
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
        ]);

        $current = DB::table('business_settings')->where('id', 1)->first();
        $currentPaymentStrip = Schema::hasColumn('business_settings', 'payment_strip')
            ? $current?->payment_strip
            : null;

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
