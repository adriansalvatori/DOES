<?php

namespace App\Application\Reporting;

use App\Domain\Delivery\Enums\Priority;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the weekly and biweekly PDF shows. Period figures cover the report window; "open" figures are a
 * snapshot taken when the report is generated.
 */
final readonly class TeamReport
{
    /**
     * @param  list<array{designer: User, points_done: int, previous_points_done: int, weekly_points_done: list<int>, approved_points: int, approved_count: int, previous_approved_points: int, proofs_to_reviewer: int, proofs_to_client: int, revisions: int, rework_pct: ?int, tasks_done: int, closed_points: int, closed_count: int, open_points: int, open_count: int, overdue_count: int, high_priority_open: int}>  $designers
     * @param  array{points_done: int, previous_points_done: int, approved_points: int, approved_count: int, previous_approved_points: int, proofs: int, revisions: int, rework_pct: ?int, tasks_done: int, closed_points: int, closed_count: int, new_points: int, new_count: int, open_points: int, open_count: int, overdue_count: int}  $team
     * @param  list<array{priority: Priority, count: int, points: int}>  $priorityMix
     * @param  Collection<int, Deliverable>  $attention
     */
    public function __construct(
        public ReportPeriod $period,
        public CarbonImmutable $generatedAt,
        public array $designers,
        public array $team,
        public array $priorityMix,
        public Collection $attention,
    ) {}
}
