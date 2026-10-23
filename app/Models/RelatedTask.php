<?php

namespace App\Models;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\SubtaskCategory;
use App\Services\AutomationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RelatedTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'order_element_id',
        'order_stage_id',
        'title',
        'type',
        'category',
        'return_core_status',
        'status',
        'assignee_id',
        'scheduled_date',
        'due_date',
        'completed_at',
        'trigger_type',
        'priority',
        'is_work_task',
        'sort_order',
    ];

    protected $casts = [
        'type' => RelatedTaskType::class,
        'return_core_status' => CoreStatus::class,
        'scheduled_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'is_work_task' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function setCategoryAttribute($value): void
    {
        $this->attributes['category'] = $value instanceof SubtaskCategory ? $value->value : $value;
    }

    public function getCategoryAttribute(): SubtaskCategory
    {
        if ($this->isFollowUp()) {
            return SubtaskCategory::MANAGEMENT;
        }

        if ($this->attributes['category'] ?? null) {
            return $this->attributes['category'] instanceof SubtaskCategory
                ? $this->attributes['category']
                : SubtaskCategory::tryFrom($this->attributes['category']) ?? SubtaskCategory::NEW_DESIGN;
        }

        return SubtaskCategory::detectFromContext($this->title ?? '', $this->order);
    }

    public function getIsWorkTaskAttribute(): bool
    {
        if ($this->isFollowUp() || ($this->attributes['category'] ?? null) === SubtaskCategory::MANAGEMENT->value) {
            return false;
        }

        return (bool) ($this->attributes['is_work_task'] ?? true);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(OrderElement::class, 'order_element_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(OrderStage::class, 'order_stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Designer::class, 'assignee_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        $words = array_values(array_filter(preg_split('/\s+/', $search), fn ($w) => $w !== ''));
        if (empty($words)) {
            return $query;
        }

        return $query->where(function ($q) use ($words) {
            foreach ($words as $word) {
                $term = '%'.addcslashes($word, '%_\\').'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', $term)
                        ->orWhereHas('order', fn ($oq) => $oq->where('company_name', 'like', $term)->orWhere('task_name', 'like', $term)->orWhere('trello_title', 'like', $term));
                });
            }
        });
    }

    protected static function booted(): void
    {
        static::created(function (RelatedTask $task) {
            if ($task->order && ! $task->order->in_workspace) {
                $task->order->update([
                    'in_workspace' => true,
                ]);
            }

            if ($task->order_id) {
                $alreadyLogged = OrderEvent::where('order_id', $task->order_id)
                    ->where('created_at', '>=', now()->subSeconds(2))
                    ->where('metadata->task_id', $task->id)
                    ->exists();

                if (! $alreadyLogged) {
                    $actor = $task->trigger_type ? 'AutomationEngine' : (auth()->user()?->name ?? 'Sistema');
                    $taskTypeStr = is_string($task->type) ? $task->type : $task->type?->value;

                    OrderEvent::create([
                        'order_id' => $task->order_id,
                        'event_type' => 'AUTOMATIC_TASK_TRIGGERED',
                        'actor' => $actor,
                        'new_value' => $task->title,
                        'metadata' => [
                            'task_id' => $task->id,
                            'task_title' => $task->title,
                            'task_type' => $taskTypeStr,
                            'trigger_type' => $task->trigger_type ?? 'SYSTEM_AUTOMATION',
                            'priority' => $task->priority ?? 'normal',
                        ],
                    ]);
                }
            }

            if ($task->order) {
                app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($task->order);
            }
        });

        static::updated(function (RelatedTask $task) {
            if ($task->order) {
                app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($task->order);
            }
        });

        static::deleted(function (RelatedTask $task) {
            if ($task->order) {
                app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($task->order);
            }
        });
    }

    public function isDone(): bool
    {
        return $this->status === 'done' || $this->completed_at !== null;
    }

    public function isSystemTask(): bool
    {
        return ! $this->is_work_task || $this->trigger_type !== null;
    }

    public function isWorkTask(): bool
    {
        if ($this->isFollowUp() || $this->category === SubtaskCategory::MANAGEMENT) {
            return false;
        }

        return (bool) $this->is_work_task && $this->trigger_type === null;
    }

    public function isNote(): bool
    {
        return $this->order_id === null;
    }

    public static function cleanTitleForOrder(string $title, Order $order): string
    {
        $rawTitle = trim($title);
        if ($rawTitle === '') {
            return '';
        }

        $candidates = array_filter([
            $order->location_text,
            $order->location_name,
            $order->clientLocation?->name,
            $order->company_name,
            $order->wo_number,
        ], fn ($val) => ! empty($val) && is_string($val));

        $terms = [];
        foreach ($candidates as $cand) {
            $candTrimmed = trim($cand);
            if ($candTrimmed !== '') {
                $terms[] = $candTrimmed;
                if (preg_match_all('/\b[A-Za-z0-9]{2,}\b/u', $candTrimmed, $matches)) {
                    foreach ($matches[0] as $token) {
                        if (! in_array(mb_strtolower($token), ['de', 'la', 'el', 'los', 'las', 'del', 'en', 'y'], true)) {
                            $terms[] = $token;
                        }
                    }
                }
            }
        }

        // Sort terms longest first to avoid partial replacements (e.g. "Talpa 16" before "16")
        usort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $terms = array_values(array_unique($terms));

        $cleaned = $rawTitle;
        foreach ($terms as $term) {
            $quoted = preg_quote($term, '/');
            // Remove with word boundary so internal letters of other words are preserved (e.g. "la" won't match inside "Camila")
            $pattern = '/\b'.$quoted.'\b/iu';
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        // Remove leftover leading/trailing punctuation, dashes, colons, pipes, and whitespace
        $cleaned = preg_replace('/^[\s\-:,|]+|[\s\-:,|]+$/u', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return $rawTitle;
        }

        // Capitalize first character
        return mb_strtoupper(mb_substr($cleaned, 0, 1)).mb_substr($cleaned, 1);
    }

    public function isFollowUp(): bool
    {
        $rawType = $this->type instanceof RelatedTaskType ? $this->type->value : (string) ($this->type ?? '');

        if (in_array($rawType, [
            RelatedTaskType::FOLLOW_UP_CLIENTE->value,
            RelatedTaskType::FOLLOW_UP_CAMILA->value,
            RelatedTaskType::FOLLOW_UP_ALTA->value,
        ], true) || ($this->trigger_type === 'CLIENT_FOLLOW_UP_CYCLE')) {
            return true;
        }

        $titleLower = mb_strtolower($this->title ?? '', 'UTF-8');

        return str_contains($titleLower, 'follow up') || str_contains($titleLower, 'followup') || str_starts_with($titleLower, 'llamar');
    }

    public function isPonerEnAlta(): bool
    {
        if ($this->isFollowUp() || ($this->attributes['category'] ?? null) === SubtaskCategory::MANAGEMENT->value) {
            return false;
        }

        $rawType = is_string($this->type) ? $this->type : $this->type?->value;
        if ($rawType === RelatedTaskType::PONER_ALTA->value) {
            return true;
        }

        if ($this->category === SubtaskCategory::PRODUCTION_ADJUSTMENTS || $this->return_core_status === CoreStatus::EN_PRODUCCION) {
            return true;
        }

        $titleUpper = mb_strtoupper($this->title ?? '', 'UTF-8');

        return str_contains($titleUpper, 'ALTA');
    }

    public function isOrderUrgent(): bool
    {
        return (bool) ($this->order && $this->order->isUrgente());
    }

    public function isUrgent(): bool
    {
        return $this->isOrderUrgent()
            || $this->priority === 'urgent'
            || $this->colorCodingKey() === 'urgent';
    }

    public function getCardBgClass(): string
    {
        if ($this->isDone()) {
            return 'bg-[#fafaf9] border border-stone-200/90 shadow-2xs opacity-75 ring-0';
        }

        if ($this->order && ($this->order->isBlocked() || $this->order->core_status === CoreStatus::ENTRANTE)) {
            return 'bg-stone-100/90 border border-stone-300 text-zinc-500 opacity-60 grayscale-[50%] shadow-none ring-0';
        }

        if ($this->isUrgent()) {
            return 'bg-gradient-to-br from-rose-50/90 via-white to-red-50/70 border-2 border-red-500/90 shadow-md ring-2 ring-red-300/40';
        }

        if (! $this->order_id) {
            return 'bg-amber-50/90 border-amber-200/90 hover:border-amber-300 cursor-pointer';
        }

        return 'bg-white border-stone-200/80 hover:border-stone-300';
    }

    /**
     * Determine the matching ColorCoding key for this subtask.
     */
    public function colorCodingKey(): string
    {
        $rawType = is_string($this->type) ? $this->type : $this->type?->value;
        $titleUpper = mb_strtoupper($this->title ?? '');

        // 1. Urgente / Atraso Preventivo
        if (
            $this->priority === 'urgent'
            || $this->trigger_type === 'AUTOMATIC_OVERDUE_DETECTION'
            || $rawType === RelatedTaskType::CORREO_ATRASO->value
            || str_contains($titleUpper, 'ATRASO')
            || str_contains($titleUpper, 'URGENTE')
        ) {
            return 'urgent';
        }

        // 2. Poner en ALTA / Producción
        if (
            in_array($rawType, [
                RelatedTaskType::PONER_ALTA->value,
                RelatedTaskType::FOLLOW_UP_ALTA->value,
                RelatedTaskType::AJUSTES_PRODUCCION->value,
            ], true)
            || $this->category === SubtaskCategory::PRODUCTION_ADJUSTMENTS
            || str_contains($titleUpper, 'ALTA')
            || str_contains($titleUpper, 'PRODUCCI')
        ) {
            return 'production';
        }

        // 3. Follow-up Camila / QA
        if (
            $rawType === RelatedTaskType::FOLLOW_UP_CAMILA->value
            || $this->category === SubtaskCategory::CAMILA_ADJUSTMENTS
            || str_contains($titleUpper, 'CAMILA')
        ) {
            return 'camila';
        }

        // 4. Follow-up / Correo Cliente / Solicitar Info
        if (
            in_array($rawType, [
                RelatedTaskType::BIENVENIDA->value,
                RelatedTaskType::SOLICITAR_INFO->value,
                RelatedTaskType::FOLLOW_UP_CLIENTE->value,
            ], true)
            || $this->category === SubtaskCategory::CLIENT_ADJUSTMENTS
            || str_contains($titleUpper, 'CLIENTE')
            || str_contains($titleUpper, 'CORREO')
            || str_contains($titleUpper, 'BIENVENIDA')
            || str_contains($titleUpper, 'SOLICITAR')
        ) {
            return 'client';
        }

        // 5. Bloqueado / Resolver
        if (
            in_array($rawType, [
                RelatedTaskType::RESOLVER->value,
                RelatedTaskType::BLOCKED->value,
            ], true)
            || str_contains($titleUpper, 'RESOLVER')
            || str_contains($titleUpper, 'BLOQUE')
        ) {
            return 'blocked';
        }

        return 'camila';
    }

    public function colorCodingCssKey(): string
    {
        return str_replace('_', '-', $this->colorCodingKey());
    }

    public function systemBadgeStyle(): string
    {
        $k = $this->colorCodingCssKey();

        return "background-color: var(--cc-{$k}-bg-light); color: var(--cc-{$k}-text-dark); border-color: var(--cc-{$k}-border);";
    }

    public function systemDotStyle(): string
    {
        $k = $this->colorCodingCssKey();

        return "background-color: var(--cc-{$k}-solid);";
    }

    public function systemTextStyle(): string
    {
        $k = $this->colorCodingCssKey();

        return "color: var(--cc-{$k}-text-dark);";
    }

    public function colorCodingLabel(): string
    {
        return match ($this->colorCodingKey()) {
            'urgent' => __('Urgente / Atraso'),
            'production' => __('ALTA / Producción'),
            'client' => __('Cliente / Seguimiento'),
            'camila' => __('Camila / QA'),
            'blocked' => __('Bloqueado / Resolver'),
            default => __('Sistema'),
        };
    }

    public function colorCodingShortLabel(): string
    {
        return match ($this->colorCodingKey()) {
            'urgent' => __('Urgente'),
            'production' => __('ALTA'),
            'client' => __('Cliente'),
            'camila' => __('QA'),
            'blocked' => __('Bloqueo'),
            default => __('Sistema'),
        };
    }
}
