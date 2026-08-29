<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GoogleMapsSettingsController extends Controller
{
    public function edit(): Response
    {
        $available = Schema::hasTable('business_settings')
            && Schema::hasColumn('business_settings', 'google_maps_api_key');
        $settings = Schema::hasTable('business_settings')
            ? DB::table('business_settings')->where('id', 1)->first()
            : null;

        return Inertia::render('Admin/BusinessSettings/GoogleMaps', [
            'available' => $available,
            'configured' => $available && filled($settings?->google_maps_api_key),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_maps_api_key' => ['nullable', 'string', 'max:255'],
            'clear_google_maps_api_key' => ['nullable', 'boolean'],
        ]);

        if (! Schema::hasTable('business_settings') || ! Schema::hasColumn('business_settings', 'google_maps_api_key')) {
            throw ValidationException::withMessages([
                'google_maps_api_key' => 'Run the latest database migrations before configuring Google Maps.',
            ]);
        }

        $clear = (bool) ($validated['clear_google_maps_api_key'] ?? false);
        $newKey = trim((string) ($validated['google_maps_api_key'] ?? ''));

        if (! $clear && $newKey === '') {
            return back()->with('success', 'Google Maps settings left unchanged.');
        }

        $payload = [
            'google_maps_api_key' => $clear ? null : $newKey,
            'updated_at' => now(),
        ];

        if (DB::table('business_settings')->where('id', 1)->exists()) {
            DB::table('business_settings')->where('id', 1)->update($payload);
        } else {
            DB::table('business_settings')->insert([
                'id' => 1,
                ...$payload,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', $clear ? 'Google Maps API key removed.' : 'Google Maps API key updated successfully.');
    }
}
