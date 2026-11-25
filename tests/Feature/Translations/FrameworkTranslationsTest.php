<?php

namespace Tests\Feature\Translations;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrameworkTranslationsTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function frameworkFiles(): array
    {
        return [
            'validation' => ['validation'],
            'auth' => ['auth'],
            'pagination' => ['pagination'],
            'passwords' => ['passwords'],
        ];
    }

    #[DataProvider('frameworkFiles')]
    public function test_spanish_framework_translation_file_exists_and_covers_every_framework_key(string $file): void
    {
        $path = lang_path("es/{$file}.php");
        $this->assertFileExists($path);

        $spanish = require $path;
        $frameworkEnglish = require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php");

        $this->assertIsArray($spanish);
        $this->assertSame(
            [],
            array_keys(array_diff_key($frameworkEnglish, $spanish)),
            "lang/es/{$file}.php is missing framework keys."
        );
    }

    public function test_validation_errors_render_in_spanish_for_the_spanish_locale(): void
    {
        app()->setLocale('es');

        try {
            validator(['companyName' => ''], ['companyName' => 'required'])->validate();
            $this->fail('Validation should have failed.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['companyName'][0];
        }

        $this->assertSame('El campo nombre de la empresa es obligatorio.', $message);
    }

    public function test_validation_errors_render_in_english_for_the_english_locale(): void
    {
        app()->setLocale('en');

        try {
            validator(['email' => ''], ['email' => 'required'])->validate();
            $this->fail('Validation should have failed.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['email'][0];
        }

        $this->assertSame('The email field is required.', $message);
    }
}
