<?php

namespace App\Application\Delivery\Data;

use Carbon\CarbonInterface;

enum PromiseHealth: string
{
    case OVERDUE = 'overdue';
    case DUE_SOON = 'due_soon';
    case ON_TRACK = 'on_track';
    case NONE = 'none';

    /**
     * Due soon means within the next two weekdays.
     */
    public static function for(?CarbonInterface $promised, bool $open, CarbonInterface $now): self
    {
        if ($promised === null || ! $open) {
            return self::NONE;
        }

        $day = $promised->copy()->startOfDay();
        $today = $now->copy()->startOfDay();

        return match (true) {
            $day->lt($today) => self::OVERDUE,
            $day->lte($today->copy()->addWeekdays(2)) => self::DUE_SOON,
            default => self::ON_TRACK,
        };
    }
}
