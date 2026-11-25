<?php

namespace Tests\Feature\Translations;

use Tests\TestCase;

class EnglishSourceKeysTest extends TestCase
{
    public function test_no_key_in_the_spanish_dictionary_is_written_in_spanish(): void
    {
        $offenders = array_values(array_filter(
            array_keys(TranslationScanner::spanishDictionary()),
            fn (string $key): bool => TranslationScanner::looksSpanish($key)
        ));

        $this->assertSame([], $offenders, count($offenders)." lang/es.json key(s) look like Spanish source text (keys must be English):\n".implode("\n", array_slice($offenders, 0, 60)));
    }

    public function test_no_key_used_in_code_is_written_in_spanish(): void
    {
        $offenders = [];
        foreach (TranslationScanner::usedKeys() as $key => $files) {
            if (TranslationScanner::looksSpanish($key)) {
                $offenders[] = $key.'  ('.$files[0].')';
            }
        }

        $this->assertSame([], $offenders, count($offenders)." key(s) used in code look like Spanish source text (keys must be English):\n".implode("\n", array_slice($offenders, 0, 60)));
    }

    public function test_the_english_overrides_do_not_contain_spanish_text_as_values(): void
    {
        $offenders = [];
        foreach (TranslationScanner::englishDictionary() as $key => $value) {
            if (TranslationScanner::looksSpanish($value)) {
                $offenders[] = "{$key} => {$value}";
            }
        }

        $this->assertSame([], $offenders, count($offenders)." lang/en.json value(s) look like Spanish:\n".implode("\n", array_slice($offenders, 0, 60)));
    }
}
