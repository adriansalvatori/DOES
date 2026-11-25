<?php

namespace Tests\Feature\Translations;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Shared helpers for the translation guard tests: finds the literal keys used in code and
 * views, loads the Spanish dictionary and offers a heuristic to spot Spanish source text.
 */
class TranslationScanner
{
    /**
     * Names that legitimately carry accents inside English source text.
     *
     * @var list<string>
     */
    public const ALLOWED_NAMES = ['Euralíz', 'Adrián', 'César', 'Guzmán', 'Camila', 'Kudos', 'Idioma / Language', 'Español', 'Spanish'];

    /**
     * Spanish words that are never valid in an English source key.
     *
     * @var list<string>
     */
    private const SPANISH_WORDS = [
        'de', 'la', 'el', 'los', 'las', 'del', 'para', 'una', 'que', 'por', 'con', 'sin', 'más', 'esta', 'este', 'pero',
        'como', 'desde', 'hasta', 'entre', 'orden', 'órdenes', 'ordenes', 'tarjeta', 'tarjetas', 'archivo', 'archivos',
        'cliente', 'clientes', 'fecha', 'nueva', 'nuevo', 'nuevas', 'nuevos', 'guardado', 'guardada', 'eliminado', 'eliminada',
        'creado', 'creada', 'actualizado', 'actualizada', 'seleccionar', 'selecciona', 'ingresa', 'debes', 'hay', 'tarea',
        'tareas', 'subtarea', 'subtareas', 'diseñador', 'estado', 'enviado', 'enviada', 'pendiente', 'pendientes', 'correo',
        'guardar', 'cancelar', 'eliminar', 'editar', 'agregar', 'añadir', 'buscar', 'todos', 'todas', 'ninguno', 'ninguna',
    ];

    /**
     * Literal translation keys used in PHP, Blade and Livewire code.
     *
     * @return array<string, list<string>> key => files using it
     */
    public static function usedKeys(): array
    {
        $paths = [base_path('app'), resource_path('views')];
        $keys = [];

        foreach ($paths as $path) {
            /** @var SplFileInfo $file */
            foreach (File::allFiles($path) as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $contents = $file->getContents();
                $pattern = '/(?:\b__|\btrans|\btrans_choice|@lang)\(\s*(?:(\')((?:\\\\.|[^\'\\\\])*)\'|(")((?:\\\\.|[^"\\\\])*)")(?=\\s*[,)])/s';

                if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER) === false) {
                    continue;
                }

                foreach ($matches as $match) {
                    $isSingle = $match[1] === "'";
                    $raw = $isSingle ? $match[2] : $match[4];

                    if (! $isSingle && (str_contains($raw, '$') || str_contains($raw, '{'))) {
                        continue;
                    }

                    $key = $isSingle ? stripcslashes(str_replace(["\\'", '\\\\'], ["'", '\\'], $raw)) : stripcslashes($raw);

                    if (self::isFileKey($key)) {
                        continue;
                    }

                    $keys[$key][] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        ksort($keys);

        return $keys;
    }

    /**
     * Keys such as "validation.required" or "auth.failed" live in lang/{locale}/*.php.
     */
    public static function isFileKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z_]+(\.[a-z_*-]+)+$/', $key);
    }

    /**
     * @return array<string, string>
     */
    public static function spanishDictionary(): array
    {
        return json_decode(file_get_contents(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public static function englishDictionary(): array
    {
        return json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Whether an English source key reads like Spanish (accents, ñ, inverted marks or Spanish words).
     */
    public static function looksSpanish(string $key): bool
    {
        $text = str_ireplace(self::ALLOWED_NAMES, '', $key);

        if (preg_match('/[áéíóúñ¿¡ÁÉÍÓÚÑ]/u', $text) === 1) {
            return true;
        }

        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count(array_intersect($words, self::SPANISH_WORDS)) > 0;
    }
}
