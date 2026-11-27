<?php

namespace App\Application\Reporting;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * A report window: one week (Monday to Sunday) or two weeks. Biweekly windows start every second Monday from a fixed
 * anchor, so the same two weeks are always reported together.
 */
final readonly class ReportPeriod
{
    public const WEEKLY = 'weekly';

    public const BIWEEKLY = 'biweekly';

    public const KINDS = [self::WEEKLY, self::BIWEEKLY];

    /**
     * The first Monday of the biweekly cycle.
     */
    public const BIWEEKLY_ANCHOR = '2026-01-05';

    public CarbonImmutable $start;

    public function __construct(public string $kind, CarbonInterface $start)
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown report period [{$kind}].");
        }

        $this->start = CarbonImmutable::instance($start)->startOfDay();
    }

    /**
     * The window of this kind that contains the given day.
     */
    public static function containing(string $kind, CarbonInterface $day): self
    {
        $monday = CarbonImmutable::instance($day)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

        if ($kind === self::BIWEEKLY && self::weeksFromAnchor($monday) % 2 !== 0) {
            $monday = $monday->subWeek();
        }

        return new self($kind, $monday);
    }

    /**
     * The last window of this kind that has fully ended.
     */
    public static function lastCompleted(string $kind, CarbonInterface $now): self
    {
        return self::containing($kind, $now)->previous();
    }

    /**
     * The most recent fully ended windows, newest first.
     *
     * @return list<self>
     */
    public static function recent(string $kind, CarbonInterface $now, int $count): array
    {
        $periods = [];
        $period = self::lastCompleted($kind, $now);

        for ($index = 0; $index < $count; $index++) {
            $periods[] = $period;
            $period = $period->previous();
        }

        return $periods;
    }

    /**
     * Whether a biweekly window starts on this day, which is when the previous one is sent.
     */
    public static function startsBiweeklyWindow(CarbonInterface $day): bool
    {
        $day = CarbonImmutable::instance($day)->startOfDay();

        return $day->isMonday() && self::weeksFromAnchor($day) % 2 === 0;
    }

    public function weeks(): int
    {
        return $this->kind === self::WEEKLY ? 1 : 2;
    }

    /**
     * First moment after the window (exclusive end).
     */
    public function end(): CarbonImmutable
    {
        return $this->start->addWeeks($this->weeks());
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->end()->subDay();
    }

    public function previous(): self
    {
        return new self($this->kind, $this->start->subWeeks($this->weeks()));
    }

    /**
     * Monday of each week in the window.
     *
     * @return list<CarbonImmutable>
     */
    public function weekStarts(): array
    {
        return array_map(fn (int $week): CarbonImmutable => $this->start->addWeeks($week), range(0, $this->weeks() - 1));
    }

    public function label(): string
    {
        return $this->start->translatedFormat('j M').' – '.$this->lastDay()->translatedFormat('j M Y');
    }

    public function title(): string
    {
        return $this->kind === self::WEEKLY ? __('Weekly report') : __('Biweekly report');
    }

    public function filename(): string
    {
        return 'kudos-'.$this->kind.'-report-'.$this->start->toDateString().'.pdf';
    }

    private static function weeksFromAnchor(CarbonImmutable $monday): int
    {
        return (int) round(CarbonImmutable::parse(self::BIWEEKLY_ANCHOR)->diffInWeeks($monday, false));
    }
}
