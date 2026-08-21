<?php

namespace App\Models;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\Substatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

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
        'client_revision_count' => 'integer',
        'internal_revision_count' => 'integer',
    ];

    public function scopeInWorkspace(Builder $query): Builder
    {
        return $query->where('in_workspace', true);
    }

    public function scopeInBacklog(Builder $query): Builder
    {
        return $query->where('in_workspace', false);
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(Designer::class);
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

        if ($this->current_due_date && ($this->current_due_date->isToday() || $this->current_due_date->isPast()) && !$this->isPaused() && $this->core_status !== CoreStatus::EN_PRODUCCION) {
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

    public function isBlocked(): bool
    {
        return $this->substatus === Substatus::BLOQUEADA;
    }
}
