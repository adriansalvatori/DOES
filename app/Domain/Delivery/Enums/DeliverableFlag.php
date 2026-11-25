<?php

namespace App\Domain\Delivery\Enums;

enum DeliverableFlag: string
{
    case URGENT = 'urgent';
    case TICKET = 'ticket';
    case PROSPECT = 'prospect';
    case EXTERNAL = 'external';
    case REPRINT = 'reprint';
    case PERMIT_SIGN = 'permit_sign';
    case BALANCE_DUE = 'balance_due';

    public function label(): string
    {
        return __('enums.deliverable_flag.'.$this->value);
    }
}
