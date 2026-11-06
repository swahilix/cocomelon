<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The product card designs the storefront can switch between.
 */
final class ProductCardStyle
{
    public const DEFAULT = 'clean';

    public const OPTIONS = [
        'clean' => [
            'name' => 'Clean & minimal',
            'description' => 'Soft grey photo frame, heart on the photo and a round cart button next to the price.',
        ],
        'trust' => [
            'name' => 'Trust & clear button',
            'description' => 'Orange price, stock and warranty line, and a full-width "Add to cart" button.',
        ],
        'compact' => [
            'name' => 'Marketplace compact',
            'description' => 'Fits more products per screen: "+" on the photo, "You save" amount and a low-stock bar.',
        ],
        'premium' => [
            'name' => 'Premium showcase',
            'description' => 'Warm brand-tinted photo frame, rounder corners, category label and a black "Add" pill.',
        ],
    ];

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
        return Schema::hasTable('business_settings') && Schema::hasColumn('business_settings', 'product_card_style');
    }

    public static function current(?object $business = null): string
    {
        if ($business !== null) {
            return self::normalize($business->product_card_style ?? null);
        }

        if (! self::available()) {
            return self::DEFAULT;
        }

        return self::normalize(DB::table('business_settings')->where('id', 1)->value('product_card_style'));
    }
}
