<?php

namespace App\Domain\Scheduling;

/**
 * Computed health of a due date. It is never stored.
 */
enum SlaState: string
{
    case ON_TRACK = 'on_track';
    case ALMOST_OVERDUE = 'almost_overdue';
    case OVERDUE = 'overdue';
}
