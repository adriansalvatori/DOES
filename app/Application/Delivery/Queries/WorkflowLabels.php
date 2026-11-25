<?php

namespace App\Application\Delivery\Queries;

use App\Domain\Delivery\Models\WorkflowLabel;

/**
 * Reads the editable labels of block, hold and close reasons and flags in any locale, falling back to the enum translation files.
 */
class WorkflowLabels
{
    public const LOCALES = ['en', 'es'];

    /** @var array<string, array<string, WorkflowLabel>>|null */
    private ?array $rows = null;

    public function label(string $kind, string $code, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $row = $this->rows()[$kind][$code] ?? null;

        if ($row !== null) {
            return $row->labelFor($locale);
        }

        return __('enums.'.$kind.'.'.$code, [], $locale);
    }

    /**
     * The code whose label, in any locale, equals a text that was stored already rendered. Null when nothing matches.
     */
    public function codeForLabel(string $kind, string $text): ?string
    {
        $needle = mb_strtolower(trim($text));

        foreach ($this->rows()[$kind] ?? [] as $code => $row) {
            if (in_array($needle, [mb_strtolower($row->label_en), mb_strtolower($row->label_es)], true)) {
                return $code;
            }
        }

        foreach (array_keys(trans('enums.'.$kind, [], 'en')) as $code) {
            foreach (self::LOCALES as $locale) {
                if (mb_strtolower(__('enums.'.$kind.'.'.$code, [], $locale)) === $needle) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, WorkflowLabel>>
     */
    private function rows(): array
    {
        return $this->rows ??= WorkflowLabel::query()->get()->groupBy('kind')
            ->map(fn ($labels) => $labels->keyBy('code')->all())
            ->all();
    }
}
