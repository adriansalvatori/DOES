<?php

namespace App\Enums;

enum BlockingReason: string
{
    case FALTAN_MEDIDAS = 'FALTAN MEDIDAS';
    case FALTA_LOGO = 'FALTA LOGO';
    case OTROS = 'OTROS';

    public function label(): string
    {
        return $this->value;
    }
}
