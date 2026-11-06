<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fonts the storefront can switch between (Admin → Settings →
 * Storefront appearance). All are free Google Fonts with Latin glyphs for
 * English and Swahili, and clear numbers for prices.
 */
final class StorefrontFont
{
    public const DEFAULT = 'inter';

    public const OPTIONS = [
        'plus-jakarta-sans' => [
            'name' => 'Plus Jakarta Sans',
            'description' => 'Modern and friendly with a premium feel. Clear prices. Recommended for a tech store.',
            'family' => "'Plus Jakarta Sans'",
            'google' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800',
            'recommended' => true,
        ],
        'inter' => [
            'name' => 'Inter',
            'description' => 'Neutral and very easy to read at small sizes. Safe, professional choice.',
            'family' => "'Inter'",
            'google' => 'Inter:wght@400;500;600;700;800',
            'recommended' => false,
        ],
        'dm-sans' => [
            'name' => 'DM Sans',
            'description' => 'Soft, rounded shapes. Calm and clean, a little warmer than Inter.',
            'family' => "'DM Sans'",
            'google' => 'DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800',
            'recommended' => false,
        ],
        'manrope' => [
            'name' => 'Manrope',
            'description' => 'Sharp, techy look. Suits gadgets and electronics.',
            'family' => "'Manrope'",
            'google' => 'Manrope:wght@400;500;600;700;800',
            'recommended' => false,
        ],
        'poppins' => [
            'name' => 'Poppins',
            'description' => 'Round and bold, popular with East African shops. Wider, so less text fits per line.',
            'family' => "'Poppins'",
            'google' => 'Poppins:wght@400;500;600;700;800',
            'recommended' => false,
        ],
    ];

    private const FALLBACK = "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";

    public static function keys(): array
    {
        return array_keys(self::OPTIONS);
    }

    public static function normalize(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::OPTIONS) ? $value : self::DEFAULT;
    }

    public static function available(): bool
    {
        return Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'storefront_font');
    }

    public static function current(?object $business = null): string
    {
        if ($business !== null) {
            return self::normalize($business->storefront_font ?? null);
        }

        try {
            if (! self::available()) {
                return self::DEFAULT;
            }

            return self::normalize(DB::table('business_settings')->where('id', 1)->value('storefront_font'));
        } catch (\Throwable) {
            return self::DEFAULT;
        }
    }

    /** CSS font-family stack, e.g. 'Inter', ui-sans-serif, … */
    public static function stack(string $key): string
    {
        return self::OPTIONS[self::normalize($key)]['family'].', '.self::FALLBACK;
    }

    /** Google Fonts stylesheet URL for one or more fonts. */
    public static function stylesheet(string ...$keys): string
    {
        $families = collect($keys ?: [self::DEFAULT])
            ->map(fn (string $key) => 'family='.self::OPTIONS[self::normalize($key)]['google'])
            ->unique()
            ->implode('&');

        return 'https://fonts.googleapis.com/css2?'.$families.'&display=swap';
    }

    public static function forAdmin(): array
    {
        return collect(self::OPTIONS)
            ->map(fn (array $font, string $key): array => [
                'value' => $key,
                'name' => $font['name'],
                'description' => $font['description'],
                'recommended' => $font['recommended'],
                'stack' => self::stack($key),
            ])
            ->values()
            ->all();
    }
}
