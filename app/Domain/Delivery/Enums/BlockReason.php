<?php

namespace App\Domain\Delivery\Enums;

use App\Application\Delivery\Queries\WorkflowLabels;

enum BlockReason: string
{
    case MISSING_MEASUREMENTS = 'missing_measurements';
    case MISSING_LOGO = 'missing_logo';
    case AWAITING_ESTIMATE_APPROVAL = 'awaiting_estimate_approval';
    case MISSING_INFO = 'missing_info';
    case CUSTOMER_SERVICE_REQUIRED = 'customer_service_required';
    case AWAITING_PERMIT = 'awaiting_permit';
    case OTHER = 'other';

    public function label(): string
    {
        return app(WorkflowLabels::class)->label('block_reason', $this->value);
    }
}
