<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reusable task: a plain work item or a gate that advances the deliverable to a status.
 */
class TaskTemplate extends Model
{
    protected $fillable = [
        'name_en',
        'name_es',
        'kind',
        'advances_to',
        'required',
        'active',
        'sort',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'advances_to' => DeliverableStatus::class,
            'required' => 'boolean',
            'active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<TaskTemplate>  $query
     * @return Builder<TaskTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function isGate(): bool
    {
        return $this->kind === 'gate';
    }

    public function nameFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) ($locale === 'es' && filled($this->name_es) ? $this->name_es : $this->name_en);
    }

    /**
     * The domain task kind this template creates.
     */
    public function taskKind(): TaskKind
    {
        if (! $this->isGate()) {
            return TaskKind::WORK;
        }

        return match ($this->advances_to) {
            DeliverableStatus::CLIENT_REVIEW => TaskKind::GATE_SEND_PROOF,
            DeliverableStatus::READY_FOR_PRODUCTION => TaskKind::GATE_PREPARE_PRODUCTION_FILES,
            default => TaskKind::GATE_CUSTOM,
        };
    }
}
