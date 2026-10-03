<?php

namespace App\Enums;

enum Substatus: string
{
    case BLOQUEADA = 'BLOQUEADA';
    case OVERDUE = 'OVERDUE';
    case ALMOST_OVERDUE = 'ALMOST OVERDUE';
    case CAMBIOS_CAMILA = 'ENVIAR A CAMILA';
    case CAMBIOS_CLIENTE = 'CAMBIOS CLIENTE';
    case PONER_EN_ALTA = 'PONER EN ALTA';
    case FALTA_APROBACION_ESTIMADO = 'FALTA APROBACIÓN DE ESTIMADO';
    case NO_RESPUESTA = 'NO RESPUESTA';
    case PAUSADO = 'PAUSADO';
    case AJUSTES_PRODUCCION = 'AJUSTES DE PRODUCCIÓN';
    case WAITING_FOR_CLIENT = 'WAITING FOR CLIENT';
    case CUSTOMER_SERVICE_REQUIRED = 'CUSTOMER SERVICE REQUIRED';
    case URGENTE = 'URGENTE';
    case ENVIADO_EN_ALTA = 'ENVIADO EN ALTA';
    case TICKET = 'TICKET';
    case POTENTIAL_CUSTOMER = 'POTENTIAL CUSTOMER';
    case ESPERANDO_PERMISO = 'ESPERANDO PERMISO';
    case MAKE_PERMIT_SIGN = 'MAKE PERMIT SIGN';
    case FALTA_INFORMACION = 'FALTA INFORMACIÓN';
    case ESPERANDO_RESPUESTA = 'ESPERANDO RESPUESTA';
    case PROCESO_DE_PERMISO = 'PROCESO DE PERMISO';
    case CANCELADA_POR_CLIENTE = 'CANCELADA POR CLIENTE';
    case CANCELADA_POR_CAMILA = 'CANCELADA POR CAMILA';
    case CANCELADA = 'CANCELADA';
    case NO_REALIZADA_TRANSFERIDA = 'NO REALIZADA / TRANSFERIDA';
    case FINALIZADA = 'FINALIZADA !';
    case EXTERNO = 'EXTERNO';

    public function label(): string
    {
        return match ($this) {
            self::URGENTE => __('Urgente'),
            self::BLOQUEADA => __('Bloqueada'),
            self::OVERDUE => __('Overdue'),
            self::ALMOST_OVERDUE => __('Casi Vencida'),
            self::CAMBIOS_CAMILA => __('Enviar a Camila'),
            self::CAMBIOS_CLIENTE => __('Cambios Cliente'),
            self::PONER_EN_ALTA => __('Poner en Alta'),
            self::FALTA_APROBACION_ESTIMADO => __('Falta Aprobación Estimado'),
            self::NO_RESPUESTA => __('No Respuesta'),
            self::PAUSADO => __('Pausado'),
            self::ESPERANDO_PERMISO => __('Esperando Permiso'),
            self::AJUSTES_PRODUCCION => __('Ajustes Producción'),
            self::WAITING_FOR_CLIENT => __('Esperando Cliente'),
            self::CUSTOMER_SERVICE_REQUIRED => __('Atención al Cliente Requerida'),
            self::ENVIADO_EN_ALTA => __('Enviado en Alta'),
            self::TICKET => __('Ticket'),
            self::POTENTIAL_CUSTOMER => __('Cliente Potencial'),
            self::MAKE_PERMIT_SIGN => __('Permiso de Firma'),
            self::FALTA_INFORMACION => __('Falta Información'),
            self::ESPERANDO_RESPUESTA => __('Esperando Respuesta'),
            self::PROCESO_DE_PERMISO => __('Proceso de Permiso'),
            self::CANCELADA_POR_CLIENTE => __('Cancelada por Cliente'),
            self::CANCELADA_POR_CAMILA => __('Cancelada por Camila'),
            self::CANCELADA => __('Cancelada'),
            self::NO_REALIZADA_TRANSFERIDA => __('No realizada / Transferida'),
            self::FINALIZADA => __('Finalizada'),
            self::EXTERNO => __('Externo'),
        };
    }

    public function customBadgeStyle(): ?string
    {
        static $dbCache = null;
        if ($dbCache === null) {
            try {
                $dbCache = \App\Models\Substatus::all()->keyBy('name');
            } catch (\Throwable $e) {
                $dbCache = collect();
            }
        }

        $subModel = $dbCache->get($this->value);
        if ($subModel && $subModel->bg_color && $subModel->text_color) {
            return "background-color: {$subModel->bg_color}; color: {$subModel->text_color}; border-color: {$subModel->border_color};";
        }

        return null;
    }

    public function getInlineBadgeStyle(): string
    {
        return $this->customBadgeStyle() ?? '';
    }

    public function getInlineBadgeStyleAttribute(): string
    {
        return $this->customBadgeStyle() ?? '';
    }

    public function badgeStyle(): string
    {
        if ($custom = $this->customBadgeStyle()) {
            return $custom;
        }

        return match ($this) {
            self::URGENTE => 'bg-red-600 text-white border-red-700 font-extrabold shadow-sm animate-pulse',
            self::BLOQUEADA => 'bg-orange-50 text-orange-700 border-orange-200 font-medium',
            self::OVERDUE => 'bg-red-50 text-red-700 border-red-200 font-semibold',
            self::ALMOST_OVERDUE => 'bg-amber-50 text-amber-700 border-amber-200 font-medium',
            self::CAMBIOS_CAMILA => 'bg-purple-50 text-purple-700 border-purple-200 font-medium',
            self::CAMBIOS_CLIENTE => 'bg-sky-50 text-sky-700 border-sky-200 font-medium',
            self::PONER_EN_ALTA => 'bg-pink-50 text-pink-700 border-pink-200 font-medium',
            self::FALTA_APROBACION_ESTIMADO => 'bg-orange-50 text-orange-700 border-orange-200 font-medium',
            self::NO_RESPUESTA => 'bg-orange-50 text-orange-700 border-orange-200 font-medium',
            self::PAUSADO => 'bg-stone-100 text-stone-600 border-stone-200 font-medium',
            self::ESPERANDO_PERMISO => 'bg-yellow-50 text-yellow-800 border-yellow-200 font-medium',
            self::AJUSTES_PRODUCCION => 'bg-pink-50 text-pink-700 border-pink-200 font-medium',
            self::WAITING_FOR_CLIENT => 'bg-sky-50 text-sky-700 border-sky-200 font-medium',
            self::CUSTOMER_SERVICE_REQUIRED => 'bg-orange-50 text-orange-700 border-orange-200 font-bold',
            self::ENVIADO_EN_ALTA => 'bg-pink-50 text-pink-700 border-pink-200 font-medium',
            self::TICKET => 'bg-[#EAD1DC] text-purple-950 border-[#D5B8C6] font-bold',
            self::POTENTIAL_CUSTOMER => 'bg-emerald-50 text-emerald-800 border-emerald-300 font-bold',
            self::MAKE_PERMIT_SIGN => 'bg-amber-100 text-amber-800 border-amber-300 font-medium',
            self::FALTA_INFORMACION => 'bg-amber-50 text-amber-700 border-amber-200 font-medium',
            self::ESPERANDO_RESPUESTA => 'bg-sky-50 text-sky-700 border-sky-200 font-medium',
            self::PROCESO_DE_PERMISO => 'bg-amber-100 text-amber-800 border-amber-300 font-medium',
            self::CANCELADA_POR_CLIENTE => 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
            self::CANCELADA_POR_CAMILA => 'bg-red-50 text-red-700 border-red-200 font-medium',
            self::CANCELADA => 'bg-red-100 text-red-800 border-red-300 font-bold',
            self::NO_REALIZADA_TRANSFERIDA => 'bg-stone-200 text-stone-700 border-stone-300 font-medium',
            self::FINALIZADA => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold',
            self::EXTERNO => 'bg-amber-100 text-amber-800 border-amber-300 font-bold',
        };
    }

    public function isGlobal(): bool
    {
        return match ($this) {
            self::TICKET, self::POTENTIAL_CUSTOMER, self::URGENTE, self::OVERDUE, self::ALMOST_OVERDUE, self::EXTERNO => true,
            default => false,
        };
    }

    public function defaultCoreStatus(): ?CoreStatus
    {
        return match ($this) {
            self::BLOQUEADA, self::FALTA_APROBACION_ESTIMADO => CoreStatus::ENTRANTE,
            self::CAMBIOS_CAMILA => CoreStatus::ENVIADO_A_CAMILA,
            self::WAITING_FOR_CLIENT, self::CAMBIOS_CLIENTE, self::NO_RESPUESTA => CoreStatus::ENVIADO_AL_CLIENTE,
            self::PAUSADO, self::ESPERANDO_PERMISO, self::CUSTOMER_SERVICE_REQUIRED => CoreStatus::ON_HOLD,
            self::ENVIADO_EN_ALTA => CoreStatus::EN_PRODUCCION,
            self::CANCELADA, self::NO_REALIZADA_TRANSFERIDA => CoreStatus::ARCHIVED,
            default => null,
        };
    }
}
