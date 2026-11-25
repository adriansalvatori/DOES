<?php

namespace App\Domain\Scheduling;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Business days are weekdays that are not registered holidays.
 */
class BusinessCalendar
{
    /**
     * @param  list<string>  $holidays  Dates as Y-m-d. The hook where a real holiday list plugs in.
     */
    public function __construct(private array $holidays = []) {}

    public function isHoliday(CarbonInterface $date): bool
    {
        return in_array($date->toDateString(), $this->holidays, true);
    }

    public function isBusinessDay(CarbonInterface $date): bool
    {
        return $date->isWeekday() && ! $this->isHoliday($date);
    }

    /**
     * The same day when it is a business day, otherwise the next one.
     */
    public function rollForward(CarbonInterface $date): CarbonImmutable
    {
        $day = CarbonImmutable::instance($date)->startOfDay();
        while (! $this->isBusinessDay($day)) {
            $day = $day->addDay();
        }

        return $day;
    }

    public function nextBusinessDay(CarbonInterface $date): CarbonImmutable
    {
        return $this->addBusinessDays($date, 1);
    }

    /**
     * Adds business days to the date. The first business day counted is the one after the start.
     */
    public function addBusinessDays(CarbonInterface $date, int $days): CarbonImmutable
    {
        $day = CarbonImmutable::instance($date)->startOfDay();
        $remaining = $days;
        while ($remaining > 0) {
            $day = $day->addDay();
            if ($this->isBusinessDay($day)) {
                $remaining--;
            }
        }

        return $day;
    }

    /**
     * Business days elapsed after the "from" day up to and including the "to" day.
     */
    public function businessDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $day = CarbonImmutable::instance($from)->startOfDay();
        $end = CarbonImmutable::instance($to)->startOfDay();
        $count = 0;
        while ($day->lt($end)) {
            $day = $day->addDay();
            if ($this->isBusinessDay($day)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Day a welcome email is due: the entry day, or the next business day when entered on a weekend or after 16:30.
     * An entry in the past counts from now.
     */
    public function welcomeEmailDate(?CarbonInterface $entry = null): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $moment = $entry ? CarbonImmutable::instance($entry) : $now;
        if ($moment->startOfDay()->lt($now->startOfDay())) {
            $moment = $now;
        }

        $pastCutoff = $moment->hour > 16 || ($moment->hour === 16 && $moment->minute >= 30);
        if (! $this->isBusinessDay($moment) || $pastCutoff) {
            return $this->nextBusinessDay($moment);
        }

        return $moment->startOfDay();
    }
}
