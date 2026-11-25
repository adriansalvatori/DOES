<?php

namespace App\Domain\Delivery\Enums;

use App\Application\Delivery\Queries\WorkflowLabels;

enum CloseReason: string
{
    case COMPLETED = 'completed';
    case CANCELLED_BY_CLIENT = 'cancelled_by_client';
    case CANCELLED_INTERNAL = 'cancelled_internal';
    case CANCELLED_BY_REVIEWER = 'cancelled_by_reviewer';
    case TRANSFERRED = 'transferred';
    case CLIENT_UNRESPONSIVE = 'client_unresponsive';

    public function label(): string
    {
        return app(WorkflowLabels::class)->label('close_reason', $this->value);
    }
}
