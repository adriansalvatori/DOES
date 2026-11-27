<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The week as Plan week shows it: task cards per person and day, plus a Next week column.
 */
final readonly class PlanWeek
{
    public const NEXT_WEEK = 'next';

    /**
     * @param  list<CarbonImmutable>  $days  Monday to Friday.
     * @param  CarbonImmutable  $nextWeek  Monday of the following week (the "Next week" column).
     * @param  Collection<int, Deliverable>  $backlog  Work to plan: ready and unplanned, or with tasks that have no day.
     * @param  list<array{assignee: ?User, limit: int, cells: array<string, array{tasks: Collection<int, Task>, deliverables: Collection<int, Deliverable>, points: int, open: int, used: int}>}>  $rows
     *                                                                                                                                                                                                    Cells keyed by Y-m-d and NEXT_WEEK; see PlanWeekQuery.
     * @param  Collection<int, Deliverable>  $blockedBacklog  Ready work that is blocked, at most 30, listed but never plannable.
     * @param  list<int>  $carriedTaskIds  Unfinished tasks from before this week, shown on today.
     */
    public function __construct(
        public CarbonImmutable $weekStart,
        public array $days,
        public CarbonImmutable $nextWeek,
        public Collection $backlog,
        public array $rows,
        public Collection $blockedBacklog = new Collection,
        public array $carriedTaskIds = [],
    ) {}

    /**
     * The cell keys in column order: Monday to Friday, then next week.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        return [...array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $this->days), self::NEXT_WEEK];
    }
}
