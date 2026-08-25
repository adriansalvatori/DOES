<?php

namespace App\Enums;

enum RelatedTaskType: string
{
    case BIENVENIDA = 'ENVIAR CORREO DE BIENVENIDA';
    case SOLICITAR_INFO = 'SOLICITAR INFORMACIÓN';
    case FOLLOW_UP_CLIENTE = 'FOLLOW UP CLIENTE';
    case FOLLOW_UP_CAMILA = 'FOLLOW UP CAMILA';
    case CORREO_ATRASO = 'ENVIAR CORREO DE ATRASO';
    case RESOLVER = 'RESOLVER';
    case PONER_ALTA = 'PONER EN ALTA';
    case FOLLOW_UP_ALTA = 'FOLLOW UP ALTA';
    case SUBTASK = 'SUBTAREA';
    case AJUSTES_PRODUCCION = 'AJUSTES DE PRODUCCIÓN';
    case BLOCKED = 'BLOQUEADO';

    public function label(): string
    {
        return __($this->value);
    }
}
