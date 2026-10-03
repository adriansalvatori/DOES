<?php

namespace App\Models;

use App\Enums\CoreStatus;
use App\Services\ColorCodingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'event_type',
        'actor',
        'previous_value',
        'new_value',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isAutomated(): bool
    {
        $type = strtoupper((string) $this->event_type);
        $newVal = strtoupper((string) $this->new_value);

        if (str_contains($type, 'STATUS_CHANGED') || str_contains($newVal, 'PRODUCCI') || str_contains($type, 'APPROVAL')) {
            return false;
        }

        $actor = strtolower((string) $this->actor);

        return str_contains($actor, 'automation')
            || str_contains($actor, 'sistema')
            || str_contains($actor, 'system')
            || str_contains($actor, 'bot')
            || str_contains($type, 'AUTOMATIC')
            || str_contains($type, 'TRIGGERED')
            || isset($this->metadata['trigger_type']);
    }

    public function getNodeHexColor(): ?string
    {
        $type = strtoupper((string) $this->event_type);
        $newVal = strtoupper((string) $this->new_value);
        $oldVal = strtoupper((string) $this->old_value);
        $actor = strtoupper((string) $this->actor);

        try {
            $colorService = app(ColorCodingService::class);

            // 1. Camila related events & actions
            if (str_contains($type, 'CAMILA') || str_contains($newVal, 'CAMILA') || str_contains($oldVal, 'CAMILA') || str_contains($actor, 'CAMILA')) {
                return $colorService->getHex('camila');
            }

            // 2. Production & ALTA
            if (str_contains($newVal, 'PRODUCCI') || str_contains($newVal, 'PRODUCTION') || str_contains($type, 'PRODUCCI')
                || str_contains($newVal, 'ALTA') || str_contains($type, 'ALTA')) {
                return $colorService->getHex('production');
            }

            // 3. Designers Queue & assignments
            if (str_contains($newVal, 'EURALIZ') || str_contains($oldVal, 'EURALIZ')) {
                return $colorService->getHex('designer_euraliz');
            }
            if (str_contains($newVal, 'ADRIAN') || str_contains($oldVal, 'ADRIAN')) {
                return $colorService->getHex('designer_adrian');
            }
            if (str_contains($newVal, 'CESAR') || str_contains($oldVal, 'CESAR')) {
                return $colorService->getHex('designer_cesar');
            }

            // 4. Workflow Core Statuses
            if (str_contains($newVal, 'TODAY') || str_contains($type, 'TODAY')) {
                return $colorService->getHex('todo_today');
            }
            if (str_contains($newVal, 'BLOQUEA') || str_contains($newVal, 'ENTRANTE') || str_contains($type, 'BLOCK') || str_contains($type, 'ESTIMADO')) {
                return $colorService->getHex('blocked');
            }
            if (str_contains($newVal, 'CLIENTE') || str_contains($type, 'CLIENTE')) {
                return $colorService->getHex('client');
            }
            if (str_contains($newVal, 'HOLD') || str_contains($type, 'HOLD') || str_contains($newVal, 'CUSTOMER SERVICE') || str_contains($newVal, 'PAUSADO')) {
                return $colorService->getHex('cs_hold');
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        return null;
    }

    public function getNodeInlineStyle(): string
    {
        $hex = $this->getNodeHexColor();
        if ($hex) {
            return "background-color: {$hex} !important; color: #ffffff !important;";
        }

        return '';
    }

    public function getLineInlineStyle(): string
    {
        $hex = $this->getNodeHexColor();
        if ($hex) {
            return "background-color: {$hex} !important; opacity: 0.35;";
        }

        return '';
    }

    public function getNodeColorClass(): string
    {
        $type = strtoupper((string) $this->event_type);
        $newVal = strtoupper((string) $this->new_value);

        if (str_contains($type, 'APPROVAL') || str_contains($type, 'APPROVED')) {
            return 'bg-emerald-500 text-white';
        }
        if (str_contains($type, 'SUBTASK_COMPLETED')) {
            return 'bg-emerald-500 text-white';
        }
        if (str_contains($type, 'UNBLOCKED') || str_contains($type, 'DESBLOQUEA')) {
            return 'bg-emerald-500 text-white';
        }
        if (str_contains($type, 'HOLD') || str_contains($newVal, 'HOLD') || str_contains($type, 'REVISION')) {
            return 'bg-amber-500 text-white';
        }
        if (str_contains($type, 'SLA') || str_contains($type, 'BREACH') || str_contains($type, 'DELAY') || str_contains($type, 'WARNING')) {
            return 'bg-rose-500 text-white';
        }
        if (str_contains($newVal, 'PRODUCCI') || str_contains($newVal, 'PRODUCTION')) {
            return 'bg-orange-500 text-white';
        }
        if (str_contains($type, 'CREATED') || str_contains($type, 'TRELLO')) {
            return 'bg-blue-500 text-white';
        }

        if ($this->isAutomated() || str_contains($type, 'SUBTASK') || str_contains($type, 'TASK')) {
            return 'bg-purple-500 text-white';
        }

        return 'bg-indigo-500 text-white';
    }

    public function getLineColorClass(): string
    {
        $type = strtoupper((string) $this->event_type);
        $newVal = strtoupper((string) $this->new_value);

        if (str_contains($type, 'UNBLOCKED') || str_contains($type, 'DESBLOQUEA')) {
            return 'bg-emerald-400';
        }
        if (str_contains($type, 'SUBTASK_COMPLETED')) {
            return 'bg-emerald-400';
        }
        if (str_contains($type, 'SUBTASK') || $this->isAutomated()) {
            return 'bg-purple-400';
        }
        if (str_contains($type, 'CREATED') || str_contains($type, 'TRELLO')) {
            return 'bg-blue-400';
        }
        if (str_contains($type, 'EMAIL') || str_contains($type, 'BIENVENIDA') || str_contains($newVal, 'CLIENTE')) {
            return 'bg-emerald-400';
        }
        if (str_contains($type, 'HOLD') || str_contains($newVal, 'HOLD') || str_contains($type, 'REVISION')) {
            return 'bg-amber-400';
        }
        if (str_contains($newVal, 'PRODUCCION') || str_contains($newVal, 'PRODUCCI') || str_contains($newVal, 'PRODUCTION')) {
            return 'bg-orange-400';
        }
        if (str_contains($type, 'APPROVAL') || str_contains($type, 'APPROVED') || str_contains($newVal, 'TODAY')) {
            return 'bg-lime-400';
        }
        if (str_contains($type, 'SLA') || str_contains($type, 'BREACH') || str_contains($type, 'DELAY')) {
            return 'bg-rose-400';
        }

        return 'bg-stone-300';
    }

    public function formatValueIfDate(?string $val): string
    {
        if (empty($val)) {
            return '';
        }

        $statusLabel = CoreStatus::tryFrom($val)?->label();
        if ($statusLabel) {
            return $statusLabel;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
            try {
                $date = Carbon::parse($val)->locale(app()->getLocale());

                return strtolower($date->translatedFormat('l j'));
            } catch (\Throwable $e) {
                return $val;
            }
        }

        return $val;
    }

    public function getFormattedTitle(): string
    {
        $type = strtoupper((string) $this->event_type);
        $newValLower = strtolower((string) $this->new_value);

        if (
            str_contains($type, 'ORDER_APPROVED') ||
            str_contains($type, 'APPROVAL_SUBMITTED') ||
            str_contains($newValLower, 'aprobado por cliente') ||
            str_contains($newValLower, 'aprobado por camila') ||
            str_contains($newValLower, 'aprobación recibida')
        ) {
            return __('Aprobación Recibida');
        }

        if (str_contains($type, 'ORDER_UNBLOCKED') || str_contains($type, 'UNBLOCKED')) {
            return __('Orden desbloqueada');
        }
        if (str_contains($type, 'AUTOMATIC_TASK') || str_contains($type, 'TASK_TRIGGERED')) {
            $taskTitle = $this->metadata['task_title'] ?? $this->new_value ?? __('Tarea');

            return __('":title" añadida', ['title' => $taskTitle]);
        }
        if (str_contains($type, 'SUBTASK_SCHEDULED')) {
            $taskTitle = $this->metadata['task_title'] ?? $this->new_value ?? __('Tarea');
            $dateStr = isset($this->metadata['date']) ? $this->formatValueIfDate($this->metadata['date']) : '';

            return __('":title" agendada', ['title' => $taskTitle]).($dateStr ? ' '.__('para el').' '.$dateStr : '');
        }
        if (str_contains($type, 'SUBTASK_COMPLETED')) {
            $taskTitle = $this->metadata['task_title'] ?? $this->new_value ?? __('Tarea');

            return __('":title" completada', ['title' => $taskTitle]);
        }
        if (str_contains($type, 'ORDER_CREATED') || str_contains($type, 'CREATED')) {
            $source = $this->metadata['source'] ?? null;
            if (! $source) {
                $actor = strtolower((string) $this->actor);
                if (str_contains($actor, 'trello')) {
                    $source = 'trello';
                } elseif ($this->order) {
                    if ($this->order->is_new_from_trello || ! empty($this->order->trello_card_id)) {
                        $source = 'trello';
                    } else {
                        $source = 'app';
                    }
                } else {
                    $source = 'app';
                }
            }

            return $source === 'trello'
                ? __('Orden creada desde Trello')
                : __('Orden creada desde la app');
        }
        if (str_contains($type, 'MOVED_TO_ON_HOLD')) {
            return __('Orden ON HOLD');
        }
        if (str_contains($type, 'DELAY_RESOLVED')) {
            return __('Atraso resuelto');
        }
        if (str_contains($type, 'WO_UPDATED')) {
            return __('WO actualizado (:new)', ['new' => $this->new_value]);
        }
        if (str_contains($type, 'DUPLICATED')) {
            return __('Orden duplicada');
        }
        if (str_contains($type, 'COMMENT')) {
            return __('Comentario de Trello agregado');
        }
        if (str_contains($type, 'STATUS_CHANGED_VIA_TRELLO')) {
            $statusLabel = $this->formatValueIfDate($this->new_value);
            if (str_contains(strtoupper((string) $this->new_value), 'PRODUCCI') || str_contains(strtoupper((string) $statusLabel), 'PRODUCCI')) {
                $statusLabel = __('ENVIADO A PRODUCCIÓN');
            }

            return __('Estatus actualizado desde Trello (:status)', ['status' => $statusLabel]);
        }
        if (str_contains($type, 'DUE_DATE_CHANGED')) {
            $dateLabel = $this->formatValueIfDate($this->new_value);

            return __('Fecha de entrega actualizada (:date)', ['date' => $dateLabel]);
        }
        if (str_contains($type, 'STATUS_CHANGED') || ! empty($this->new_value)) {
            $formatted = $this->formatValueIfDate($this->new_value);
            if (str_contains(strtoupper((string) $this->new_value), 'PRODUCCI') || str_contains(strtoupper((string) $formatted), 'PRODUCCI')) {
                return __('ENVIADO A PRODUCCIÓN');
            }

            return $formatted;
        }

        return __($this->event_type);
    }

    public function getDisplayDate(): string
    {
        return $this->created_at ? $this->created_at->format('d M, g:i A') : '';
    }

    public function getFormattedTitleHtml(): string
    {
        $title = $this->getFormattedTitle();

        return (string) preg_replace(
            '/((?:completad[ao]s?|completed)(?:\s*[✓✔])?|[✓✔])/iu',
            '<span class="text-emerald-600 font-semibold">$1</span>',
            e($title)
        );
    }
}
