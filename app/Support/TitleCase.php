<?php

namespace App\Support;

/**
 * Displays names that are stored in uppercase (companies, contacts, locations) in smart title case.
 *
 * "KUDOS PRINT MEDIA" becomes "Kudos Print Media", "TALPA 14 MARIETTA - PODWER SPRING" keeps its
 * numbers and dashes, short words such as "de" or "of" stay lowercase after the first word, and
 * acronyms such as "LLC", "ATL" or "S.R.S" stay uppercase.
 */
class TitleCase
{
    /** @var list<string> */
    private const SMALL_WORDS = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'e', 'o', 'u', 'en', 'con', 'por', 'para', 'a', 'al', 'of', 'and', 'the', 'for', 'in', 'on', 'at', 'to', 'by'];

    /** @var list<string> */
    private const ACRONYMS = ['LLC', 'INC', 'CO', 'ATL', 'USA', 'US', 'BC', 'AI', 'DTF', 'NCR', 'QR', 'TV', 'DJ', 'CEO', 'GA', 'FL', 'NY', 'TX', 'CA', 'II', 'III', 'IV', 'SA', 'SRL', 'LTD', 'PVC', 'LED', 'ADA', 'DOES', 'WO'];

    public static function from(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || mb_strtoupper($value, 'UTF-8') !== $value) {
            return $value;
        }

        $words = preg_split('/(\s+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $position = 0;

        foreach ($words as $index => $word) {
            if ($word === '' || preg_match('/^\s+$/u', $word)) {
                continue;
            }

            $words[$index] = self::word($word, $position === 0);
            $position++;
        }

        return implode('', $words);
    }

    private static function word(string $word, bool $isFirst): string
    {
        $bare = trim($word, '()[]{}"\'.,:;!?™®');

        if ($bare === '' || preg_match('/\d/u', $bare) || str_contains($bare, '.') || in_array($bare, self::ACRONYMS, true)) {
            return $word;
        }

        $lower = mb_strtolower($word, 'UTF-8');

        if (! $isFirst && in_array(mb_strtolower($bare, 'UTF-8'), self::SMALL_WORDS, true)) {
            return $lower;
        }

        return preg_replace_callback(
            '/(^|[-\/("])(\p{L})/u',
            fn (array $match): string => $match[1].mb_strtoupper($match[2], 'UTF-8'),
            $lower,
        ) ?? $lower;
    }
}
