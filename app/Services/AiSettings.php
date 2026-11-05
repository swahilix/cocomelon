<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * AI and barcode lookup settings saved in the admin panel. They override the
 * matching .env values at runtime; anything left empty keeps the .env value.
 */
class AiSettings
{
    private const CACHE_KEY = 'ai-settings.v1';

    /** Original .env values, captured before any override. */
    private static ?array $envDefaults = null;

    /**
     * Apply saved settings to config. Safe to call on every request.
     */
    public static function apply(): void
    {
        // Never during console commands such as config:cache, which would write
        // the decrypted key into the cached config file. Tests still apply it.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        self::$envDefaults = [
            'api_key' => config('services.openai.api_key'),
            'model' => config('services.openai.model'),
            'product_intelligence_enabled' => config('services.openai.product_intelligence_enabled'),
            'product_formatter_enabled' => config('services.openai.product_formatter_enabled'),
            'upcitemdb_enabled' => config('services.upcitemdb.enabled'),
            'upcitemdb_key' => config('services.upcitemdb.key'),
        ];

        $saved = self::saved();
        if ($saved === []) {
            return;
        }

        $overrides = array_filter([
            'services.openai.api_key' => self::decrypt($saved['openai_api_key'] ?? null),
            'services.openai.model' => $saved['openai_model'] ?? null,
            'services.openai.product_intelligence_enabled' => self::bool($saved['ai_product_intelligence_enabled'] ?? null),
            'services.openai.product_formatter_enabled' => self::bool($saved['ai_description_formatter_enabled'] ?? null),
            'services.upcitemdb.enabled' => self::bool($saved['upcitemdb_enabled'] ?? null),
            'services.upcitemdb.key' => self::decrypt($saved['upcitemdb_key'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');

        if ($overrides !== []) {
            config($overrides);
        }
    }

    /**
     * Where the OpenAI key currently in use comes from: "admin", "env" or null.
     */
    public static function apiKeySource(): ?string
    {
        if (filled(self::decrypt(self::saved()['openai_api_key'] ?? null))) {
            return 'admin';
        }

        return filled(self::$envDefaults['api_key'] ?? null) ? 'env' : null;
    }

    public static function upcKeySource(): ?string
    {
        if (filled(self::decrypt(self::saved()['upcitemdb_key'] ?? null))) {
            return 'admin';
        }

        return filled(self::$envDefaults['upcitemdb_key'] ?? null) ? 'env' : null;
    }

    public static function envDefault(string $key): mixed
    {
        return self::$envDefaults[$key] ?? null;
    }

    /**
     * @return array<string, mixed> the raw saved row values (encrypted keys stay encrypted)
     */
    public static function saved(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function (): array {
                if (! Schema::hasTable('business_settings') || ! Schema::hasColumn('business_settings', 'openai_api_key')) {
                    return [];
                }

                $row = DB::table('business_settings')->where('id', 1)->first([
                    'openai_api_key', 'openai_model', 'ai_product_intelligence_enabled',
                    'ai_description_formatter_enabled', 'upcitemdb_enabled', 'upcitemdb_key',
                ]);

                return $row ? (array) $row : [];
            });
        } catch (Throwable) {
            // No database yet (fresh install, build step): keep .env values.
            return [];
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function mask(?string $key): ?string
    {
        if (blank($key)) {
            return null;
        }

        return str_repeat('•', 8).substr((string) $key, -4);
    }

    public static function decrypt(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function bool(mixed $value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }
}
