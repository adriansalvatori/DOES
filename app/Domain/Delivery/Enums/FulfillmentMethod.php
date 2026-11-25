<?php

namespace App\Domain\Delivery\Enums;

enum FulfillmentMethod: string
{
    case DELIVERY = 'delivery';
    case INSTALLATION = 'installation';
    case PICKUP = 'pickup';
    case SHIPPING = 'shipping';
    case OTHER = 'other';

    public function label(): string
    {
        return __('enums.fulfillment_method.'.$this->value);
    }
}
