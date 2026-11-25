<?php

namespace App\Domain\Delivery\Enums;

enum ApprovalSource: string
{
    case CLIENT = 'client';
    case REVIEWER = 'reviewer';
    case MANAGER = 'manager';

    public function label(): string
    {
        return __('enums.approval_source.'.$this->value);
    }
}
