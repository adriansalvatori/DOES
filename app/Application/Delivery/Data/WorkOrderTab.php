<?php

namespace App\Application\Delivery\Data;

enum WorkOrderTab: string
{
    case ACTIVE = 'active';
    case PRODUCTION = 'production';
    case FULFILLMENT = 'fulfillment';
    case DONE = 'done';
}
