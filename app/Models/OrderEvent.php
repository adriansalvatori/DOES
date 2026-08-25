<?php

namespace App\Models;

use App\Enums\CoreStatus;
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

    public function getNodeColorClass(): string
    {
        $type = strtoupper((string) $this->event_type);
        $newVal = strtoupper((string) $this->new_value);

        if (str_contains($type, 'UNBLOCKED') || str_contains($type, 'DESBLOQUEA')) {
            return 'bg-emerald-500 ring-4 ring-emerald-100 text-white';
        }
        if (str_contains($type, 'SUBTASK_COMPLETED')) {
            return 'bg-emerald-500 ring-4 ring-emerald-100 text-white';
        }
        if (str_contains($type, 'SUBTASK')) {
            return 'bg-purple-500 ring-4 ring-purple-100 text-white';
        }
        if (str_contains($type, 'CREATED') || str_contains($type, 'TRELLO')) {
            return 'bg-blue-500 ring-4 ring-blue-100 text-white';
        }
        if (str_contains($type, 'EMAIL') || str_contains($type, 'BIENVENIDA') || str_contains($newVal, 'CLIENTE')) {
            return 'bg-emerald-500 ring-4 ring-emerald-100 text-white';
        }
        if (str_contains($type, 'HOLD') || str_contains($newVal, 'HOLD') || str_contains($type, 'REVISION')) {
            return 'bg-amber-500 ring-4 ring-amber-100 text-white';
        }
        if (str_contains($type, 'APPROVAL') || str_contains($type, 'APPROVED') || str_contains($newVal, 'PRODUCCION') || str_contains($newVal, 'TODAY')) {
            return 'bg-lime-500 ring-4 ring-lime-100 text-white';
        }
        if (str_contains($type, 'SLA') || str_contains($type, 'BREACH') || str_contains($type, 'DELAY') || str_contains($type, 'WARNING')) {
            return 'bg-rose-500 ring-4 ring-rose-100 text-white';
        }

        return 'bg-indigo-500 ring-4 ring-indigo-100 text-white';
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
        if (str_contains($type, 'SUBTASK')) {
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
        if (str_contains($type, 'APPROVAL') || str_contains($type, 'APPROVED') || str_contains($newVal, 'PRODUCCION') || str_contains($newVal, 'TODAY')) {
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

        if (str_contains($type, 'ORDER_UNBLOCKED') || str_contains($type, 'UNBLOCKED')) {
            return __('Orden desbloqueada');
        }
        if (str_contains($type, 'AUTOMATIC_TASK') || str_contains($type, 'TASK_TRIGGERED')) {
            $taskTitle = $this->metadata['task_title'] ?? $this->new_value ?? __('Tarea');

            return __('Tarea automática ":title" gatillada', ['title' => $taskTitle]);
        }
        if (str_contains($type, 'SUBTASK_SCHEDULED')) {
            $taskTitle = $this->metadata['task_title'] ?? $this->new_value ?? __('Subtarea');
            $dateStr = isset($this->metadata['date']) ? $this->formatValueIfDate($this->metadata['date']) : '';

            return __('Subtarea ":title" agendada', ['title' => $taskTitle]).($dateStr ? ' '.__('para el').' '.$dateStr : '');
        }
        if (str_contains($type, 'SUBTASK_COMPLETED')) {
            $taskTitle = $this->metadata['task_title'] ?? $this->new_value ?? __('Subtarea');

            return __('Subtarea ":title" completada ✓', ['title' => $taskTitle]);
        }
        if (str_contains($type, 'ORDER_CREATED') || str_contains($type, 'CREATED')) {
            return __('Orden creada en flujo / Trello');
        }
        if (str_contains($type, 'MOVED_TO_ON_HOLD')) {
            return __('Movido a ON HOLD');
        }
        if (str_contains($type, 'APPROVAL_SUBMITTED')) {
            return __('Diseño aprobado por cliente');
        }
        if (str_contains($type, 'DELAY_RESOLVED')) {
            return __('Atraso resuelto y nueva fecha acordada');
        }
        if (str_contains($type, 'STATUS_CHANGED') || ! empty($this->new_value)) {
            $statusLabel = $this->formatValueIfDate($this->new_value);

            return __('Movido a :status', ['status' => $statusLabel]);
        }

        return __($this->event_type);
    }

    public function getDisplayDate(): string
    {
        $type = strtoupper((string) $this->event_type);

        if (str_contains($type, 'SUBTASK')) {
            $dateStr = $this->metadata['date'] ?? null;

            if (! $dateStr && isset($this->metadata['task_id'])) {
                $subtask = RelatedTask::find($this->metadata['task_id']);
                if ($subtask && $subtask->scheduled_date) {
                    $dateStr = $subtask->scheduled_date->toDateString();
                }
            }

            if ($dateStr) {
                try {
                    $date = Carbon::parse($dateStr)->locale(app()->getLocale());

                    return $date->translatedFormat('d M');
                } catch (\Throwable $e) {
                    // fallback
                }
            }
        }

        return $this->created_at->format('d M, g:i A');
    }
}
