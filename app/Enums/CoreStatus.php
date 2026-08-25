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
    case ARCHIVED = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::ENTRANTE => __('BLOCKED'),
            self::EURALIZ_ORDERS_RECEIVED => __('Euralíz Orders Received'),
            self::ADRIAN_ORDERS_RECEIVED => __('Adrián Orders Received'),
            self::CESAR_ORDERS_RECEIVED => __('César Orders Received'),
            self::TO_DO_TODAY => __('Working Today'),
            self::ENVIADO_A_CAMILA => __('Sent to Camila'),
            self::ENVIADO_AL_CLIENTE => __('Sent to Client'),
            self::ON_HOLD => __('On Hold'),
            self::EN_PRODUCCION => __('In Production'),
            self::ARCHIVED => __('Archived'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ENTRANTE => 'orange',
            self::EURALIZ_ORDERS_RECEIVED => 'pink',
            self::ADRIAN_ORDERS_RECEIVED => 'emerald',
            self::CESAR_ORDERS_RECEIVED => 'sky',
            self::TO_DO_TODAY => 'yellow',
            self::ENVIADO_A_CAMILA => 'purple',
            self::ENVIADO_AL_CLIENTE => 'sky',
            self::ON_HOLD => 'orange',
            self::EN_PRODUCCION => 'pink',
            self::ARCHIVED => 'stone',
        };
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::ENTRANTE => 'bg-orange-50 text-orange-700 border-orange-200',
            self::EURALIZ_ORDERS_RECEIVED => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
            self::ADRIAN_ORDERS_RECEIVED => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            self::CESAR_ORDERS_RECEIVED => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            self::TO_DO_TODAY => 'bg-amber-50 text-amber-800 border-amber-300 font-semibold',
            self::ENVIADO_A_CAMILA => 'bg-purple-50 text-purple-700 border-purple-200',
            self::ENVIADO_AL_CLIENTE => 'bg-sky-50 text-sky-700 border-sky-200',
            self::ON_HOLD => 'bg-stone-100 text-stone-700 border-stone-200',
            self::EN_PRODUCCION => 'bg-pink-50 text-pink-700 border-pink-200',
            self::ARCHIVED => 'bg-stone-100 text-stone-600 border-stone-200',
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
