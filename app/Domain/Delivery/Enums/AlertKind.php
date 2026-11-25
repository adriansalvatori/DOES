<?php

namespace App\Domain\Delivery\Enums;

enum AlertKind: string
{
    case WELCOME_EMAIL = 'welcome_email';
    case CLIENT_FOLLOW_UP_1 = 'client_follow_up_1';
    case CLIENT_FOLLOW_UP_2 = 'client_follow_up_2';
    case CLIENT_FOLLOW_UP_3 = 'client_follow_up_3';
    case REVIEWER_FOLLOW_UP = 'reviewer_follow_up';
    case DELAY_NOTICE = 'delay_notice';
    case REQUEST_INFO = 'request_info';
    case TASK_REOPENED = 'task_reopened';

    public function label(): string
    {
        return __('enums.alert_kind.'.$this->value);
    }
}
