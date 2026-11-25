<?php

namespace App\Domain\Delivery\Enums;

enum HoldReason: string
{
    case PAUSED = 'paused';
    case NO_RESPONSE = 'no_response';
    case AWAITING_PERMIT = 'awaiting_permit';
    case PERMIT_IN_PROGRESS = 'permit_in_progress';

    public function label(): string
    {
        return __('enums.hold_reason.'.$this->value);
    }
}
