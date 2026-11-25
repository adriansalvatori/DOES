<?php

namespace App\Support;

/**
 * Formats numbers with the separators of the active locale (Spanish: "1.234,50", English: "1,234.50").
 */
class LocalizedNumber
{
    public static function format(int|float $number, int $decimals = 0, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'es'
            ? number_format($number, $decimals, ',', '.')
            : number_format($number, $decimals, '.', ',');
    }
}
