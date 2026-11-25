<?php

namespace App\Domain\Delivery\Enums;

enum Area: string
{
    case DESIGN = 'design';
    case PRODUCTION = 'production';
    case FULFILLMENT = 'fulfillment';

    public function label(): string
    {
        return __('enums.area.'.$this->value);
    }
}
