<?php

namespace App\Application\Delivery\Queries\Support;

use Carbon\CarbonInterface;

/**
 * A deliverable is overdue from 16:00 on its due day, and from the start of the next day on.
 */
final class OverdueCutoff
{
    public const HOUR = 16;

    /**
     * Latest `due_target` date (inclusive) that already counts as overdue.
     */
    public static function lastOverdueDate(CarbonInterface $now): string
    {
        return $now->hour >= self::HOUR
            ? $now->toDateString()
            : $now->copy()->subDay()->toDateString();
    }
}
