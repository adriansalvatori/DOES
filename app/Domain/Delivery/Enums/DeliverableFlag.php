<?php

namespace App\Domain\Delivery\Enums;

use App\Application\Delivery\Queries\WorkflowLabels;

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
        return app(WorkflowLabels::class)->label('deliverable_flag', $this->value);
    }
}
