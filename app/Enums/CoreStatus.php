<?php

namespace App\Enums;

enum CoreStatus: string
{
    case ENTRANTE = 'ENTRANTE';
    case EURALIZ_ORDERS_RECEIVED = 'EURALIZ ORDERS RECEIVED';
    case ADRIAN_ORDERS_RECEIVED = 'ADRIAN ORDERS RECEIVED';
    case CESAR_ORDERS_RECEIVED = 'CESAR ORDERS RECEIVED';
    case TO_DO_TODAY = 'TO DO TODAY';
    case ENVIADO_A_CAMILA = 'ENVIADO A CAMILA';
    case ENVIADO_AL_CLIENTE = 'ENVIADO AL CLIENTE';
    case ON_HOLD = 'ON HOLD';
    case EN_PRODUCCION = 'EN PRODUCCIÓN';

    public function label(): string
    {
        return match ($this) {
            self::ENTRANTE => 'Bloqueadas',
            self::EURALIZ_ORDERS_RECEIVED => 'Euralíz Orders Received',
            self::ADRIAN_ORDERS_RECEIVED => 'Adrián Orders Received',
            self::CESAR_ORDERS_RECEIVED => 'César Orders Received',
            self::TO_DO_TODAY => 'Working Today',
            self::ENVIADO_A_CAMILA => 'Enviado a Camila',
            self::ENVIADO_AL_CLIENTE => 'Enviado al Cliente',
            self::ON_HOLD => 'On Hold',
            self::EN_PRODUCCION => 'En Producción',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ENTRANTE => 'rose',
            self::EURALIZ_ORDERS_RECEIVED,
            self::ADRIAN_ORDERS_RECEIVED,
            self::CESAR_ORDERS_RECEIVED => 'indigo',
            self::TO_DO_TODAY => 'amber',
            self::ENVIADO_A_CAMILA => 'purple',
            self::ENVIADO_AL_CLIENTE => 'sky',
            self::ON_HOLD => 'amber',
            self::EN_PRODUCCION => 'emerald',
        };
    }

    public static function isPendingDesign(self $status): bool
    {
        return in_array($status, [
            self::EURALIZ_ORDERS_RECEIVED,
            self::ADRIAN_ORDERS_RECEIVED,
            self::CESAR_ORDERS_RECEIVED,
        ]);
    }
}
