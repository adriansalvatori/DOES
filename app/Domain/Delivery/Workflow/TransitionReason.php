<?php

namespace App\Domain\Delivery\Workflow;

enum TransitionReason: string
{
    case MANUAL = 'manual';
    case TASK_COMPLETED = 'task_completed';
    case SCHEDULE = 'schedule';
    case DAILY_CYCLE = 'daily_cycle';
    case IMPORT = 'import';
    case SYSTEM = 'system';

    public function label(): string
    {
        return __('enums.transition_reason.'.$this->value);
    }
}
