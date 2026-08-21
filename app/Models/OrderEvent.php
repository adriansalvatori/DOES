<?php

namespace App\Models;

use App\Enums\CoreStatus;
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

    public function getFormattedTitle(): string
    {
        $type = strtoupper((string) $this->event_type);

        if (str_contains($type, 'ORDER_CREATED') || str_contains($type, 'CREATED')) {
            return 'Orden creada en flujo / Trello';
        }
        if (str_contains($type, 'MOVED_TO_ON_HOLD')) {
            return 'Movido a ON HOLD';
        }
        if (str_contains($type, 'APPROVAL_SUBMITTED')) {
            return 'Diseño aprobado por cliente';
        }
        if (str_contains($type, 'DELAY_RESOLVED')) {
            return 'Atraso resuelto y nueva fecha acordada';
        }
        if (str_contains($type, 'STATUS_CHANGED') || ! empty($this->new_value)) {
            $statusLabel = CoreStatus::tryFrom($this->new_value)?->label() ?? $this->new_value;

            return "Movido a {$statusLabel}";
        }

        return $this->event_type;
    }
}
