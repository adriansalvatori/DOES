<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class PlanWeek
{
    /**
     * @param  list<CarbonImmutable>  $days  Monday to Friday.
     * @param  Collection<int, Deliverable>  $backlog
     * @param  Collection<int, Deliverable>  $blockedBacklog  Ready work that is blocked, at most 30, listed but never plannable.
     * @param  list<array{assignee: User, limit: int, cells: array<string, array{cards: Collection<int, Deliverable>, used: int}>}>  $rows  Cells keyed by Y-m-d.
     */
    public function __construct(
        public CarbonImmutable $weekStart,
        public array $days,
        public Collection $backlog,
        public array $rows,
        public Collection $blockedBacklog = new Collection,
    ) {}
}
