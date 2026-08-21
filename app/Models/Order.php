<?php

namespace App\Models;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\Substatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'trello_card_id',
        'trello_created_at',
        'wo_number',
        'company_name',
        'responsible_person',
        'task_name',
        'trello_title',
        'designer_id',
        'core_status',
        'substatus',
        'blocking_reason',
        'blocking_reason_other',
        'start_date',
        'original_due_date',
        'current_due_date',
        'scheduled_date',
        'last_meaningful_update',
        'client_last_response',
        'approved',
        'measures_confirmed',
        'estimate_approved',
        'client_revision_count',
        'internal_revision_count',
        'pause_reason',
        'done_today',
        'customer_service_required',
        'in_workspace',
        'is_new_from_trello',
    ];

    protected $casts = [
        'trello_created_at' => 'datetime',
        'core_status' => CoreStatus::class,
        'substatus' => Substatus::class,
        'blocking_reason' => BlockingReason::class,
        'start_date' => 'date',
        'original_due_date' => 'date',
        'current_due_date' => 'date',
        'scheduled_date' => 'date',
        'last_meaningful_update' => 'datetime',
        'client_last_response' => 'datetime',
        'approved' => 'boolean',
        'measures_confirmed' => 'boolean',
        'estimate_approved' => 'boolean',
        'done_today' => 'boolean',
        'customer_service_required' => 'boolean',
        'in_workspace' => 'boolean',
        'is_new_from_trello' => 'boolean',
        'client_revision_count' => 'integer',
        'internal_revision_count' => 'integer',
    ];

    public function scopeInWorkspace(Builder $query): Builder
    {
        return $query->where('in_workspace', true);
    }

    public function scopeActiveInWorkspace(Builder $query): Builder
    {
        return $query->where('in_workspace', true)
            ->where('core_status', '!=', CoreStatus::EN_PRODUCCION);
    }

    public function scopeInBacklog(Builder $query): Builder
    {
        return $query->where('in_workspace', false);
    }

    public function scopeNewFromTrello(Builder $query): Builder
    {
        return $query->where('is_new_from_trello', true);
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(Designer::class);
    }

    public function designers(): BelongsToMany
    {
        return $this->belongsToMany(Designer::class, 'designer_order')->withTimestamps();
    }

    public function getAssignedDesignersAttribute()
    {
        $designers = $this->designers;
        if ($designers->isEmpty() && $this->designer) {
            return collect([$this->designer]);
        }

        return $designers;
    }

    public function syncDesigners(array $designerIds): void
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $designerIds))));

        if (! empty($cleanIds)) {
            // Check if any selected designer is an External Designer
            $hasExternal = Designer::whereIn('id', $cleanIds)
                ->get()
                ->contains(fn ($d) => $d->color_type === 'yellow');

            if ($hasExternal) {
                // Find Euralíz designer ID
                $euralizId = Designer::where('name', 'like', '%Eural%')
                    ->orWhere('name', 'like', '%Bravo%')
                    ->value('id') ?? 1;

                if (! in_array($euralizId, $cleanIds)) {
                    $cleanIds[] = $euralizId;
                }
            }
        }

        $this->designers()->sync($cleanIds);

        // Keep primary designer_id updated for backwards compatibility
        $primaryId = reset($cleanIds) ?: null;
        if ($this->designer_id !== $primaryId) {
            $this->updateQuietly(['designer_id' => $primaryId]);
        }
    }

    public function relatedTasks(): HasMany
    {
        return $this->hasMany(RelatedTask::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at', 'desc');
    }

    public function dueDateHistories(): HasMany
    {
        return $this->hasMany(DueDateHistory::class)->orderBy('created_at', 'desc');
    }

    public function isOverdue(): bool
    {
        if ($this->substatus === Substatus::OVERDUE) {
            return true;
        }

        if ($this->current_due_date && ($this->current_due_date->isToday() || $this->current_due_date->isPast()) && ! $this->isPaused() && $this->core_status !== CoreStatus::EN_PRODUCCION) {
            return true;
        }

        return false;
    }

    public function isPaused(): bool
    {
        return $this->core_status === CoreStatus::ON_HOLD || $this->substatus === Substatus::PAUSADO;
    }

    public function getTrelloUrlAttribute(): ?string
    {
        return $this->trello_card_id ? "https://trello.com/c/{$this->trello_card_id}" : null;
    }

    public function scopePrioritizeUrgente(Builder $query): Builder
    {
        return $query->orderByRaw("CASE WHEN substatus = 'URGENTE' THEN 0 ELSE 1 END");
    }

    public function isBlocked(): bool
    {
        return $this->substatus === Substatus::BLOQUEADA;
    }

    public function isUrgente(): bool
    {
        return $this->substatus === Substatus::URGENTE || (is_string($this->substatus) ? $this->substatus === 'URGENTE' : $this->substatus?->value === 'URGENTE');
    }

    public function getSubstatusInlineStyleAttribute(): string
    {
        $name = is_string($this->substatus) ? $this->substatus : $this->substatus?->value;
        if (! $name) {
            return 'background-color: #f3f4f6; color: #374151; border-color: #e5e7eb;';
        }

        $style = \App\Models\Substatus::getStyleFor($name);

        return $style['inline'];
    }

    public function getDesignerBadgeStyle(): string
    {
        if (! $this->designer) {
            return 'bg-amber-100 text-amber-800 border-amber-300 font-semibold';
        }

        return $this->designer->badge_style;
    }

    public function getDesignerDotColorClass(): string
    {
        if (! $this->designer) {
            return 'bg-amber-400';
        }

        return $this->designer->dot_color_class;
    }
}
