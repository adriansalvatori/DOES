<?php

namespace App\Enums;

enum Substatus: string
{
    case BLOQUEADA = 'BLOQUEADA';
    case OVERDUE = 'OVERDUE';
    case ALMOST_OVERDUE = 'ALMOST OVERDUE';
    case CAMBIOS_CAMILA = 'CAMBIOS CAMILA';
    case CAMBIOS_CLIENTE = 'CAMBIOS CLIENTE';
    case PONER_EN_ALTA = 'PONER EN ALTA';
    case FALTA_APROBACION_ESTIMADO = 'FALTA APROBACIÓN DE ESTIMADO';
    case NO_RESPUESTA = 'NO RESPUESTA';
    case PAUSADO = 'PAUSADO';
    case AJUSTES_PRODUCCION = 'AJUSTES DE PRODUCCIÓN';
    case WAITING_FOR_CLIENT = 'WAITING FOR CLIENT';
    case CUSTOMER_SERVICE_REQUIRED = 'CUSTOMER SERVICE REQUIRED';
    case URGENTE = 'URGENTE';

    public function label(): string
    {
        return $this->value;
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::URGENTE => 'bg-red-600 text-white border-red-700 font-extrabold shadow-sm animate-pulse',
            self::BLOQUEADA => 'bg-rose-50 text-rose-700 border-rose-200 font-medium',
            self::OVERDUE => 'bg-red-50 text-red-700 border-red-200 font-semibold',
            self::ALMOST_OVERDUE => 'bg-amber-50 text-amber-700 border-amber-200 font-medium',
            self::CAMBIOS_CAMILA => 'bg-purple-50 text-purple-700 border-purple-200 font-medium',
            self::CAMBIOS_CLIENTE => 'bg-sky-50 text-sky-700 border-sky-200 font-medium',
            self::PONER_EN_ALTA => 'bg-emerald-50 text-emerald-700 border-emerald-200 font-medium',
            self::FALTA_APROBACION_ESTIMADO => 'bg-orange-50 text-orange-700 border-orange-200 font-medium',
            self::NO_RESPUESTA => 'bg-stone-100 text-stone-600 border-stone-200 font-medium',
            self::PAUSADO => 'bg-stone-100 text-stone-600 border-stone-200 font-medium',
            self::AJUSTES_PRODUCCION => 'bg-teal-50 text-teal-700 border-teal-200 font-medium',
            self::WAITING_FOR_CLIENT => 'bg-blue-50 text-blue-700 border-blue-200 font-medium',
            self::CUSTOMER_SERVICE_REQUIRED => 'bg-pink-50 text-pink-700 border-pink-200 font-bold',
        };
    }
}
