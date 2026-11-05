<?php

namespace App\Support;

/**
 * GTIN / EAN / UPC barcode helpers.
 */
class Gtin
{
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function isValid(string $gtin): bool
    {
        if (! preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/', $gtin)) {
            return false;
        }

        $digits = array_map('intval', str_split($gtin));
        $checkDigit = array_pop($digits);
        $sum = 0;

        foreach (array_reverse($digits) as $index => $digit) {
            $sum += $digit * ($index % 2 === 0 ? 3 : 1);
        }

        return ((10 - ($sum % 10)) % 10) === $checkDigit;
    }
}
