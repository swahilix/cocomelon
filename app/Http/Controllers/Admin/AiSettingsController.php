<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    public function edit(): Response
    {
        $available = Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'openai_api_key');
        $saved = AiSettings::saved();
        $savedKey = AiSettings::decrypt($saved['openai_api_key'] ?? null);
        $activeKey = config('services.openai.api_key');

        return Inertia::render('Admin/BusinessSettings/AiSettings', [
            'available' => $available,
            'openai' => [
                'key_source' => AiSettings::apiKeySource(),
                'key_hint' => AiSettings::mask($savedKey ?: $activeKey),
                'model' => $saved['openai_model'] ?? '',
                'default_model' => (string) AiSettings::envDefault('model'),
                'active_model' => (string) config('services.openai.model'),
                'product_intelligence_enabled' => (bool) config('services.openai.product_intelligence_enabled'),
                'product_formatter_enabled' => (bool) config('services.openai.product_formatter_enabled'),
            ],
            'upcitemdb' => [
                'enabled' => (bool) config('services.upcitemdb.enabled'),
                'key_source' => AiSettings::upcKeySource(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'openai_api_key' => ['nullable', 'string', 'min:20', 'max:300', 'regex:/^\S+$/'],
            'clear_openai_api_key' => ['boolean'],
            'openai_model' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-\/]+$/'],
            'ai_product_intelligence_enabled' => ['required', 'boolean'],
            'ai_description_formatter_enabled' => ['required', 'boolean'],
            'upcitemdb_enabled' => ['required', 'boolean'],
            'upcitemdb_key' => ['nullable', 'string', 'max:300', 'regex:/^\S+$/'],
            'clear_upcitemdb_key' => ['boolean'],
        ], [
            'openai_api_key.min' => 'That does not look like a complete OpenAI API key.',
            'openai_api_key.regex' => 'The API key must not contain spaces.',
            'openai_model.regex' => 'Enter a model name such as gpt-5.4-mini.',
        ]);

        if (! Schema::hasTable('business_settings') || ! Schema::hasColumn('business_settings', 'openai_api_key')) {
            throw ValidationException::withMessages(['openai_api_key' => 'Run the latest database migrations before configuring AI settings.']);
        }

        $payload = [
            'openai_model' => filled($validated['openai_model'] ?? null) ? trim($validated['openai_model']) : null,
            'ai_product_intelligence_enabled' => (bool) $validated['ai_product_intelligence_enabled'],
            'ai_description_formatter_enabled' => (bool) $validated['ai_description_formatter_enabled'],
            'upcitemdb_enabled' => (bool) $validated['upcitemdb_enabled'],
            'updated_at' => now(),
        ];

        // Keys are only replaced when a new one is typed, or removed when asked.
        if ($validated['clear_openai_api_key'] ?? false) {
            $payload['openai_api_key'] = null;
        } elseif (filled($validated['openai_api_key'] ?? null)) {
            $payload['openai_api_key'] = Crypt::encryptString(trim($validated['openai_api_key']));
        }

        if ($validated['clear_upcitemdb_key'] ?? false) {
            $payload['upcitemdb_key'] = null;
        } elseif (filled($validated['upcitemdb_key'] ?? null)) {
            $payload['upcitemdb_key'] = Crypt::encryptString(trim($validated['upcitemdb_key']));
        }

        if (DB::table('business_settings')->where('id', 1)->exists()) {
            DB::table('business_settings')->where('id', 1)->update($payload);
        } else {
            DB::table('business_settings')->insert(['id' => 1, ...$payload, 'created_at' => now()]);
        }

        AiSettings::forget();

        return back()->with('success', 'AI settings saved.');
    }

    /**
     * Check that the OpenAI key works and the model is available. This only
     * looks up the model, so it does not use any paid tokens.
     */
    public function test(): RedirectResponse
    {
        $key = config('services.openai.api_key');
        $model = (string) config('services.openai.model');

        if (blank($key)) {
            return back()->with('error', 'No OpenAI API key is set yet. Paste one and save first.');
        }

        try {
            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->withToken((string) $key)
                ->acceptJson()
                ->timeout(15)
                ->get('/models/'.rawurlencode($model));
        } catch (ConnectionException) {
            return back()->with('error', 'Could not reach OpenAI from the server. Check the server\'s internet connection.');
        }

        return match (true) {
            $response->successful() => back()->with('success', "Connected to OpenAI. The key works and the model {$model} is available."),
            $response->status() === 401 => back()->with('error', 'OpenAI rejected the API key. Check that it was copied correctly and has not been revoked.'),
            $response->status() === 404 => back()->with('error', "The key works, but the model {$model} is not available to your account. Choose another model."),
            $response->status() === 429 => back()->with('error', 'OpenAI says the account is over its limit or has no credit. Check billing on platform.openai.com.'),
            default => back()->with('error', 'OpenAI returned an error ('.$response->status().'). Try again in a moment.'),
        };
    }
}
