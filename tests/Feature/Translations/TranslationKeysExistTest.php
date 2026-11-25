<?php

namespace Tests\Feature\Translations;

use Tests\TestCase;

class TranslationKeysExistTest extends TestCase
{
    /**
     * Keys that intentionally render identically in both languages and need no Spanish entry.
     *
     * @var list<string>
     */
    private const IDENTICAL_IN_BOTH_LANGUAGES = [];

    public function test_every_literal_translation_key_used_in_code_exists_in_the_spanish_dictionary(): void
    {
        $dictionary = TranslationScanner::spanishDictionary();

        $missing = [];
        foreach (TranslationScanner::usedKeys() as $key => $files) {
            if (array_key_exists($key, $dictionary) || in_array($key, self::IDENTICAL_IN_BOTH_LANGUAGES, true)) {
                continue;
            }

            $missing[] = $key.'  ('.$files[0].')';
        }

        $this->assertSame(
            [],
            $missing,
            count($missing)." translation key(s) are used in code but missing from lang/es.json:\n".implode("\n", array_slice($missing, 0, 80))
        );
    }

    public function test_the_dictionaries_are_valid_and_spanish_values_are_not_empty(): void
    {
        foreach (TranslationScanner::spanishDictionary() as $key => $value) {
            $this->assertIsString($value, "lang/es.json value for [{$key}] must be a string.");
            $this->assertNotSame('', trim($value), "lang/es.json value for [{$key}] is empty.");
        }
    }

    public function test_placeholders_are_preserved_in_spanish_translations(): void
    {
        $broken = [];
        foreach (TranslationScanner::spanishDictionary() as $key => $value) {
            preg_match_all('/:[a-z_]+/', $key, $sourcePlaceholders);
            foreach (array_unique($sourcePlaceholders[0]) as $placeholder) {
                if (! str_contains($value, $placeholder)) {
                    $broken[] = "{$key} => {$value} (missing {$placeholder})";
                }
            }
        }

        $this->assertSame([], $broken, "Spanish translations dropped placeholders:\n".implode("\n", array_slice($broken, 0, 40)));
    }
}
