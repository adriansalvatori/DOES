<?php

namespace App\Models;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Services\AutomationEngine;
use App\Services\TrelloSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'trello_card_id',
        'trello_created_at',
        'wo_number',
        'company_name',
        'location_name',
        'client_id',
        'client_location_id',
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
        'last_sent_to_client_at',
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
        'is_missing_from_trello',
        'pending_wo_number',
        'archived_at',
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
        'last_sent_to_client_at' => 'datetime',
        'approved' => 'boolean',
        'measures_confirmed' => 'boolean',
        'estimate_approved' => 'boolean',
        'done_today' => 'boolean',
        'customer_service_required' => 'boolean',
        'in_workspace' => 'boolean',
        'is_new_from_trello' => 'boolean',
        'is_missing_from_trello' => 'boolean',
        'client_revision_count' => 'integer',
        'internal_revision_count' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function scopeInWorkspace(Builder $query): Builder
    {
        return $query->where('in_workspace', true)
            ->where('core_status', '!=', CoreStatus::ARCHIVED);
    }

    public function scopeActiveInWorkspace(Builder $query): Builder
    {
        return $query->where('in_workspace', true)
            ->whereNotIn('core_status', [CoreStatus::EN_PRODUCCION, CoreStatus::ARCHIVED]);
    }

    public function scopeInBacklog(Builder $query): Builder
    {
        return $query->where('in_workspace', false)
            ->where('core_status', '!=', CoreStatus::ARCHIVED);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('core_status', CoreStatus::ARCHIVED);
    }

    public function getCleanTaskNameAttribute(): string
    {
        $task = $this->task_name ?: $this->trello_title ?: '';

        // Strip company_name or client name if task_name starts with it
        $cName = $this->company_name ?: $this->client?->name;
        if ($cName && str_starts_with(mb_strtolower($task, 'UTF-8'), mb_strtolower($cName, 'UTF-8')) && mb_strlen($task, 'UTF-8') > mb_strlen($cName, 'UTF-8')) {
            $task = trim(mb_substr($task, mb_strlen($cName, 'UTF-8'), null, 'UTF-8'), " \t\n\r\0\x0B-:");
        }

        if (empty($task)) {
            return $this->location_name ?: ($this->task_name ?: __('Proyecto sin nombre'));
        }

        return $task;
    }

    public function getLocationTextAttribute(): ?string
    {
        return $this->location_name ?: $this->clientLocation?->name;
    }

    public function getDaysToCloseAttribute(): ?int
    {
        if (! $this->archived_at) {
            return null;
        }

        $start = $this->start_date ? Carbon::parse($this->start_date)->startOfDay() : $this->created_at->startOfDay();
        $closed = $this->archived_at->startOfDay();

        return (int) max(0, $start->diffInDays($closed));
    }

    public function scopeNewFromTrello(Builder $query): Builder
    {
        return $query->where('is_new_from_trello', true);
    }

    public function hasNoWo(): bool
    {
        if (empty($this->wo_number)) {
            return true;
        }

        $clean = trim(preg_replace('/^WO\s*/i', '', $this->wo_number));

        return empty($clean) || (bool) preg_match('/^0+$/', $clean);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientLocation(): BelongsTo
    {
        return $this->belongsTo(ClientLocation::class, 'client_location_id');
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

    public function isDueToday(): bool
    {
        if ($this->done_today || ! $this->in_workspace || $this->isPaused() || $this->core_status === CoreStatus::EN_PRODUCCION || $this->core_status === CoreStatus::ENVIADO_AL_CLIENTE) {
            return false;
        }

        return (bool) ($this->current_due_date && $this->current_due_date->isToday());
    }

    public function isAlmostOverdue(): bool
    {
        if (! $this->isDueToday()) {
            return false;
        }

        return now()->hour < 16;
    }

    public function isArchived(): bool
    {
        return $this->core_status === CoreStatus::ARCHIVED;
    }

    public function isSlaExempt(): bool
    {
        if ($this->done_today || ! $this->in_workspace || $this->isPaused()) {
            return true;
        }

        return in_array($this->core_status, [
            CoreStatus::ENVIADO_AL_CLIENTE,
            CoreStatus::EN_PRODUCCION,
            CoreStatus::ON_HOLD,
            CoreStatus::ARCHIVED,
        ], true);
    }

    public function isOverdue(): bool
    {
        if ($this->isSlaExempt()) {
            return false;
        }

        if (! $this->current_due_date) {
            return false;
        }

        if ($this->substatus === Substatus::OVERDUE) {
            return true;
        }

        if ($this->current_due_date->isPast() && ! $this->current_due_date->isToday()) {
            return true;
        }

        if ($this->current_due_date->isToday() && now()->hour >= 16) {
            return true;
        }

        return false;
    }

    public function getDesignerOrdersReceivedStatus(): CoreStatus
    {
        $designerName = $this->designer?->name ?? $this->designers->first()?->name;

        return match ($designerName) {
            'Adrián' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'César' => CoreStatus::CESAR_ORDERS_RECEIVED,
            default => CoreStatus::EURALIZ_ORDERS_RECEIVED,
        };
    }

    public function getPrimaryDesignerId(): ?int
    {
        if ($this->designer_id) {
            $designer = Designer::find($this->designer_id);
            if ($designer && $designer->active) {
                return $designer->id;
            }
        }

        $firstPivot = $this->designers->first(fn ($d) => $d->active);
        if ($firstPivot) {
            return $firstPivot->id;
        }

        if ($this->designer_id) {
            return $this->designer_id;
        }

        return Designer::where('active', true)->first()?->id ?? Designer::first()?->id;
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
        return $this->substatus === Substatus::BLOQUEADA
            || $this->substatus === Substatus::FALTA_APROBACION_ESTIMADO
            || (bool) $this->customer_service_required
            || $this->blocking_reason !== null;
    }

    public function block(?string $reason = null, ?string $reasonOther = null, ?string $comment = null, bool $requireCS = false, ?string $actor = null): void
    {
        $previousStatus = $this->core_status;
        $blockingEnum = null;

        if ($reason) {
            $blockingEnum = BlockingReason::tryFrom($reason) ?? BlockingReason::OTROS;
        }

        $substatus = ($reason === 'FALTA APROBACIÓN DE ESTIMADO' || $reason === 'FALTA_APROBACION_ESTIMADO')
            ? Substatus::FALTA_APROBACION_ESTIMADO
            : Substatus::BLOQUEADA;

        $this->update([
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => $substatus,
            'blocking_reason' => $blockingEnum,
            'blocking_reason_other' => $reasonOther ?: ($reason === 'OTROS' ? $comment : null),
            'customer_service_required' => $requireCS,
        ]);

        if ($requireCS) {
            $taskTitle = "Resolver bloqueo: {$this->company_name} - {$this->task_name}";
            if ($comment) {
                $taskTitle .= " ({$comment})";
            }

            RelatedTask::create([
                'order_id' => $this->id,
                'title' => $taskTitle,
                'type' => RelatedTaskType::SOLICITAR_INFO,
                'status' => 'todo',
                'assignee_id' => $this->getPrimaryDesignerId(),
                'due_date' => now()->toDateString(),
                'priority' => 'high',
            ]);
        }

        OrderEvent::create([
            'order_id' => $this->id,
            'event_type' => 'ORDER_BLOCKED',
            'actor' => $actor ?? auth()->user()?->name ?? 'Usuario',
            'previous_value' => $previousStatus?->value,
            'new_value' => CoreStatus::ENTRANTE->value,
            'metadata' => [
                'substatus' => $substatus->value,
                'reason' => $reason,
                'reason_other' => $reasonOther,
                'comment' => $comment ?? 'Orden marcada como Bloqueada.',
                'customer_service_required' => $requireCS,
            ],
        ]);

        app(AutomationEngine::class)->handleStatusChanged($this, $previousStatus, CoreStatus::ENTRANTE);

        if ($this->trello_card_id) {
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($this);
            } catch (\Throwable $e) {
                // Ignore network error
            }
        }
    }

    public function unblock(string $reason, ?string $actor = null): void
    {
        $previousStatus = $this->core_status;

        $lastBlockedEvent = $this->events()
            ->where(function ($q) {
                $q->where('event_type', 'ORDER_BLOCKED')
                    ->orWhere('new_value', CoreStatus::ENTRANTE->value)
                    ->orWhere('metadata->substatus', Substatus::BLOQUEADA->value);
            })
            ->latest()
            ->first();

        $blockedAt = $lastBlockedEvent ? $lastBlockedEvent->created_at : ($this->updated_at ?? now());
        $now = now();
        $diffInHours = max(1, (int) ceil($blockedAt->diffInHours($now)));
        $diffInDays = max(1, (int) ceil($blockedAt->diffInDays($now)));

        if ($diffInHours < 24) {
            $durationText = $diffInHours <= 1 ? __('menos de 1 hora') : __(':hours horas', ['hours' => $diffInHours]);
        } else {
            $durationText = $diffInDays === 1 ? __('1 día') : __(':days días', ['days' => $diffInDays]);
        }

        $designerStatus = match ($this->designer?->name) {
            'Adrián' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'César' => CoreStatus::CESAR_ORDERS_RECEIVED,
            default => CoreStatus::EURALIZ_ORDERS_RECEIVED,
        };

        $targetStatus = ($this->core_status === CoreStatus::ENTRANTE) ? $designerStatus : ($this->core_status ?? $designerStatus);

        $newSubstatus = ($this->substatus === Substatus::BLOQUEADA || $this->substatus === Substatus::FALTA_APROBACION_ESTIMADO) ? null : $this->substatus;

        $this->update([
            'core_status' => $targetStatus,
            'substatus' => $newSubstatus,
            'blocking_reason' => null,
            'blocking_reason_other' => null,
            'customer_service_required' => false,
        ]);

        $this->relatedTasks()
            ->where('status', 'todo')
            ->whereIn('type', [RelatedTaskType::BLOCKED, RelatedTaskType::RESOLVER])
            ->update(['status' => 'done']);

        OrderEvent::create([
            'order_id' => $this->id,
            'event_type' => 'ORDER_UNBLOCKED',
            'actor' => $actor ?? auth()->user()?->name ?? 'Usuario',
            'previous_value' => $previousStatus?->value,
            'new_value' => $targetStatus->value,
            'metadata' => [
                'reason' => $reason,
                'blocked_days' => $diffInDays,
                'blocked_duration' => $durationText,
                'comment' => "Desbloqueado tras {$durationText}: {$reason}",
            ],
        ]);

        app(AutomationEngine::class)->handleStatusChanged($this, $previousStatus, $targetStatus);

        if ($this->trello_card_id) {
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($this);
            } catch (\Throwable $e) {
                // Ignore network error
            }
        }
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

    public function isApproved(): bool
    {
        return (bool) ($this->approved || $this->substatus === Substatus::PONER_EN_ALTA);
    }

    public function isInProduction(): bool
    {
        return $this->core_status === CoreStatus::EN_PRODUCCION || $this->substatus === Substatus::AJUSTES_PRODUCCION || $this->substatus === Substatus::ENVIADO_EN_ALTA;
    }

    public function getCardBgClass(): string
    {
        if ($this->is_missing_from_trello) {
            return 'bg-stone-100/90 border-2 border-dashed border-stone-300 text-stone-500 opacity-70 grayscale shadow-none';
        }

        if ($this->isBlocked() || $this->core_status === CoreStatus::ENTRANTE) {
            return 'bg-stone-100/90 border border-stone-300 text-zinc-500 opacity-60 grayscale-[50%] shadow-none ring-0';
        }

        if ($this->done_today) {
            return 'bg-[#fafaf9] border border-stone-200/90 shadow-2xs opacity-75 ring-0';
        }

        if ($this->isUrgente()) {
            return 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40';
        }

        if ($this->isOverdue()) {
            return 'bg-rose-50 border border-red-400 hover:border-red-500';
        }

        if ($this->isDueToday()) {
            return 'bg-amber-50 border border-amber-300 hover:border-amber-400';
        }

        if ($this->isInProduction() || $this->isApproved()) {
            return 'bg-pink-50/80 border border-pink-300 hover:border-pink-400 shadow-2xs';
        }

        return 'bg-white border border-[#e9e9e7] hover:border-stone-300';
    }

    public static function getActionRequiredCount(): int
    {
        $blockedOrdersCount = static::inWorkspace()
            ->where(function ($q) {
                $q->where('substatus', Substatus::BLOQUEADA)
                    ->orWhere('substatus', Substatus::FALTA_APROBACION_ESTIMADO)
                    ->orWhere('customer_service_required', true)
                    ->orWhere(function ($dt) {
                        $dt->where('done_today', true)
                            ->whereNotIn('core_status', [
                                CoreStatus::ENVIADO_A_CAMILA,
                                CoreStatus::ENVIADO_AL_CLIENTE,
                                CoreStatus::EN_PRODUCCION,
                                CoreStatus::ON_HOLD,
                                CoreStatus::ARCHIVED,
                            ]);
                    })
                    ->orWhere(function ($m) {
                        $m->where('approved', true)->where('measures_confirmed', false);
                    });
            })->count();

        $resolverTasksCount = RelatedTask::whereHas('order', function ($q) {
            $q->inWorkspace()->whereNotIn('core_status', [
                CoreStatus::ENVIADO_A_CAMILA,
                CoreStatus::ENVIADO_AL_CLIENTE,
                CoreStatus::EN_PRODUCCION,
                CoreStatus::ON_HOLD,
                CoreStatus::ARCHIVED,
            ]);
        })
            ->where('status', 'todo')
            ->whereIn('type', [
                RelatedTaskType::RESOLVER,
                RelatedTaskType::SOLICITAR_INFO,
                RelatedTaskType::CORREO_ATRASO,
            ])->count();

        return $blockedOrdersCount + $resolverTasksCount;
    }
}
