<?php

namespace App\Domain\Delivery\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Editable English and Spanish label for a code of one of the workflow enums.
 */
class WorkflowLabel extends Model
{
    public const KINDS = ['block_reason', 'hold_reason', 'close_reason', 'deliverable_flag'];

    protected $fillable = [
        'kind',
        'code',
        'label_en',
        'label_es',
        'active',
        'sort',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * The label in a locale, English when the locale has none.
     */
    public function labelFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) ($locale === 'es' && filled($this->label_es) ? $this->label_es : $this->label_en);
    }
}
