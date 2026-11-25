<?php

namespace Tests\Feature\Translations;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

class BladeRawTextTest extends TestCase
{
    /**
     * Flip to true once the Blade views have been fully moved to __() / @lang.
     */
    private const VIEWS_FULLY_TRANSLATED = true;

    /**
     * Brand names, technical tokens and key names that may appear as raw text in any language.
     *
     * @var list<string>
     */
    private const ALLOWED_TOKENS = [
        'Kudos', 'DOES', 'WO', 'CSV', 'QR', 'PDF', 'URL', 'ID', 'Trello', 'Google', 'Drive', 'WhatsApp', 'Email', 'SLA',
        'ALTA', 'OK', 'Excel', 'QuickBooks', 'EN', 'ES', 'API', 'JSON', 'SQLite', 'HEX', 'Esc', 'Enter', 'Ctrl', 'Shift',
        'Tab', 'Alt', 'IMG', 'DOC', 'Markdown', 'KB', 'MB', 'GB', 'HTML', 'Flux', 'Livewire', 'Español', 'English', 'PNG', 'JPG',
        'WEBP', 'DD', 'MM', 'YYYY', 'Print', 'Media',
    ];

    /**
     * Lines that are data rather than translatable copy (postal address, env variable names, file extensions).
     *
     * @var list<string>
     */
    private const ALLOWED_LINE_PATTERNS = ['/Norcross, GA/', '/^cs\\s*\\.com$/', '/GOOGLE_DRIVE_CLIENT_/', '/^[•·\\s]*$/u'];

    /**
     * Views that are not part of the application UI (framework scaffolding).
     *
     * @var list<string>
     */
    private const IGNORED_VIEWS = ['welcome.blade.php'];

    public function test_blade_views_have_no_raw_text_outside_translation_helpers(): void
    {
        if (! self::VIEWS_FULLY_TRANSLATED) {
            $this->markTestSkipped('Skipped until the views rewrite is finished: set VIEWS_FULLY_TRANSLATED to true to enforce it.');
        }

        $offenders = [];

        /** @var SplFileInfo $file */
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php') || in_array($file->getFilename(), self::IGNORED_VIEWS, true)) {
                continue;
            }

            foreach ($this->rawTextNodes($file->getContents()) as $text) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).': '.$text;
            }
        }

        $this->assertSame([], $offenders, count($offenders)." raw text node(s) found in Blade views (wrap them in __()):\n".implode("\n", array_slice($offenders, 0, 80)));
    }

    public function test_the_text_extractor_finds_raw_text_and_ignores_translated_text(): void
    {
        $blade = <<<'BLADE'
        {{-- a comment with words --}}
        <div class="a" x-data="{ open: a > b }">
            <span>{{ __('Translated') }}</span>
            <span>Raw words here</span>
            @if ($a > 1) <b>{!! $html !!}</b> @endif
            <script>const text = 'script words';</script>
            <p>@lang('Also translated')</p>
        </div>
        BLADE;

        $this->assertSame(['Raw words here'], $this->rawTextNodes($blade));
    }

    /**
     * Text nodes containing letters that sit outside {{ }}, {!! !!}, @lang, PHP blocks, scripts and tags.
     *
     * @return list<string>
     */
    private function rawTextNodes(string $blade): array
    {
        $stripped = preg_replace([
            '/\{\{--.*?--\}\}/s',
            '/<!--.*?-->/s',
            '/<\?php.*?\?>/s',
            '/<!DOCTYPE[^>]*>/i',
            '/@php\b.*?@endphp/s',
            '/@verbatim.*?@endverbatim/s',
            '/<script\b.*?<\/script>/is',
            '/<style\b.*?<\/style>/is',
            '/\{\{.*?\}\}/s',
            '/\{!!.*?!!\}/s',
            '/@lang\((?:[^()]|\([^()]*\))*\)/s',
            '/@[A-Za-z]+(?:\s*(\((?:[^()]++|(?1))*\)))?/s',
            '/<\/?[A-Za-z][\w:.-]*(?:\s+(?:"[^"]*"|\'[^\']*\'|[^>"\'])*)?\/?>/s',
        ], ' ', $blade) ?? $blade;

        $nodes = [];
        foreach (preg_split('/\R/', $stripped) ?: [] as $line) {
            $text = trim(preg_replace('/\b('.implode('|', self::ALLOWED_TOKENS).')\b/', '', html_entity_decode($line)) ?? '');

            if ($text !== '' && preg_match('/\p{L}{2,}/u', $text) === 1 && ! $this->isAllowedLine($text)) {
                $nodes[] = $text;
            }
        }

        return $nodes;
    }

    private function isAllowedLine(string $text): bool
    {
        foreach (self::ALLOWED_LINE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
