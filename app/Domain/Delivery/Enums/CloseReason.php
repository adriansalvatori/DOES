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
    case INACTIVE_ON_HOLD = 'inactive_on_hold';

    /**
     * Reasons a person can pick when closing by hand. Archiving idle holds is left to the daily cycle.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $reason): bool => $reason !== self::INACTIVE_ON_HOLD));
    }

    public function label(): string
    {
        return app(WorkflowLabels::class)->label('close_reason', $this->value);
    }
}
