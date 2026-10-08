<?php

namespace App\Enums;

use App\Models\Order;

enum SubtaskCategory: string
{
    case CLIENT_ADJUSTMENTS = 'client_adjustments';
    case CAMILA_ADJUSTMENTS = 'camila_adjustments';
    case PRODUCTION_ADJUSTMENTS = 'production_adjustments';
    case NEW_DESIGN = 'new_design';
    case MANAGEMENT = 'management';

    public function label(): string
    {
        return match ($this) {
            self::NEW_DESIGN => __('Volver al estado anterior'),
            self::CLIENT_ADJUSTMENTS => __('Enviado al Cliente'),
            self::CAMILA_ADJUSTMENTS => __('Enviado a Camila'),
            self::PRODUCTION_ADJUSTMENTS => __('En Producción'),
            self::MANAGEMENT => __('No mover la orden'),
        };
    }

    public function returnActionLabel(): string
    {
        return match ($this) {
            self::NEW_DESIGN => __('Volver al estado anterior'),
            self::CLIENT_ADJUSTMENTS => __('Enviado al Cliente'),
            self::CAMILA_ADJUSTMENTS => __('Enviado a Camila'),
            self::PRODUCTION_ADJUSTMENTS => __('En Producción'),
            self::MANAGEMENT => __('No mover la orden'),
        };
    }

    public function shortReturnLabel(): string
    {
        return match ($this) {
            self::NEW_DESIGN => __('Estado previo'),
            self::CLIENT_ADJUSTMENTS => __('Al Cliente'),
            self::CAMILA_ADJUSTMENTS => __('A Camila'),
            self::PRODUCTION_ADJUSTMENTS => __('A Producción'),
            self::MANAGEMENT => __('Sin cambio'),
        };
    }

    public function dotColorClass(): string
    {
        return match ($this) {
            self::NEW_DESIGN => 'bg-emerald-500',
            self::CLIENT_ADJUSTMENTS => 'bg-blue-500',
            self::CAMILA_ADJUSTMENTS => 'bg-purple-500',
            self::PRODUCTION_ADJUSTMENTS => 'bg-pink-500',
            self::MANAGEMENT => 'bg-stone-400',
        };
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::CLIENT_ADJUSTMENTS => 'bg-sky-50 text-sky-700 border-sky-200 hover:bg-sky-100',
            self::CAMILA_ADJUSTMENTS => 'bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100',
            self::PRODUCTION_ADJUSTMENTS => 'bg-pink-50 text-pink-700 border-pink-200 hover:bg-pink-100',
            self::NEW_DESIGN => 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
            self::MANAGEMENT => 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100',
        };
    }

    public function textColorClass(): string
    {
        return match ($this) {
            self::CLIENT_ADJUSTMENTS => 'text-sky-700 hover:text-sky-800',
            self::CAMILA_ADJUSTMENTS => 'text-purple-700 hover:text-purple-800',
            self::PRODUCTION_ADJUSTMENTS => 'text-pink-700 hover:text-pink-800',
            self::NEW_DESIGN => 'text-emerald-700 hover:text-emerald-800',
            self::MANAGEMENT => 'text-amber-700 hover:text-amber-800',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::CLIENT_ADJUSTMENTS => 'bg-sky-50 text-sky-700 border-sky-300 font-bold',
            self::CAMILA_ADJUSTMENTS => 'bg-purple-50 text-purple-700 border-purple-300 font-bold',
            self::PRODUCTION_ADJUSTMENTS => 'bg-pink-50 text-pink-700 border-pink-300 font-bold',
            self::NEW_DESIGN => 'bg-emerald-50 text-emerald-700 border-emerald-300 font-bold',
            self::MANAGEMENT => 'bg-amber-50 text-amber-700 border-amber-300 font-bold',
        };
    }

    public function triggerSubstatus(): ?Substatus
    {
        return match ($this) {
            self::CLIENT_ADJUSTMENTS => Substatus::CAMBIOS_CLIENTE,
            self::CAMILA_ADJUSTMENTS => Substatus::CAMBIOS_CAMILA,
            self::PRODUCTION_ADJUSTMENTS => Substatus::AJUSTES_PRODUCCION,
            default => null,
        };
    }

    public function defaultReturnCoreStatus(): ?CoreStatus
    {
        return match ($this) {
            self::CLIENT_ADJUSTMENTS => CoreStatus::ENVIADO_AL_CLIENTE,
            self::CAMILA_ADJUSTMENTS => CoreStatus::ENVIADO_A_CAMILA,
            self::PRODUCTION_ADJUSTMENTS => CoreStatus::EN_PRODUCCION,
            default => null,
        };
    }

    public function defaultReturnSubstatus(): ?Substatus
    {
        return match ($this) {
            self::CLIENT_ADJUSTMENTS => Substatus::WAITING_FOR_CLIENT,
            self::CAMILA_ADJUSTMENTS => Substatus::CAMBIOS_CAMILA,
            self::PRODUCTION_ADJUSTMENTS => Substatus::ENVIADO_EN_ALTA,
            default => null,
        };
    }

    public static function detectFromContext(string $title = '', ?Order $order = null): self
    {
        $titleLower = mb_strtolower(trim($title), 'UTF-8');

        // Check for Follow Up or Management keywords FIRST so they don't get misclassified by client/camila
        if (preg_match('/\b(follow\s*up|followup|llamar?|medidas?|survey|confirmar|solicitar)\b/u', $titleLower)) {
            return self::MANAGEMENT;
        }

        // 1. Explicit keyword matching takes precedence when specific keywords are present
        if (preg_match('/\b(cliente|client|proof)\b/u', $titleLower)) {
            return self::CLIENT_ADJUSTMENTS;
        }

        if (preg_match('/\b(camila)\b/u', $titleLower)) {
            return self::CAMILA_ADJUSTMENTS;
        }

        if (preg_match('/\b(producci[oó]n|alta|taller)\b/u', $titleLower)) {
            return self::PRODUCTION_ADJUSTMENTS;
        }

        // 2. Fall back to current order status context
        if ($order) {
            if ($order->core_status === CoreStatus::ENVIADO_AL_CLIENTE) {
                return self::CLIENT_ADJUSTMENTS;
            }

            if ($order->core_status === CoreStatus::ENVIADO_A_CAMILA) {
                return self::CAMILA_ADJUSTMENTS;
            }

            if ($order->core_status === CoreStatus::EN_PRODUCCION) {
                return self::PRODUCTION_ADJUSTMENTS;
            }

            // If order was previously in client or camila before TO DO TODAY
            if ($order->core_status === CoreStatus::TO_DO_TODAY) {
                if ($order->origin_core_status === CoreStatus::ENVIADO_AL_CLIENTE->value || $order->substatus === Substatus::CAMBIOS_CLIENTE) {
                    return self::CLIENT_ADJUSTMENTS;
                }
                if ($order->origin_core_status === CoreStatus::ENVIADO_A_CAMILA->value || $order->substatus === Substatus::CAMBIOS_CAMILA) {
                    return self::CAMILA_ADJUSTMENTS;
                }
                if ($order->origin_core_status === CoreStatus::EN_PRODUCCION->value || $order->substatus === Substatus::AJUSTES_PRODUCCION) {
                    return self::PRODUCTION_ADJUSTMENTS;
                }
            }
        }

        return self::NEW_DESIGN;
    }
}
