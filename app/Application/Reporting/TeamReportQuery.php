<?php

namespace App\Application\Reporting;

use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\Priority;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the weekly and biweekly team report. Work is credited to the deliverable's assignee. Tasks carry the
 * Fibonacci points and a deliverable's points are the sum of its tasks.
 *
 * - Points done: points of the tasks completed in the window (the team's velocity). Status moves are read from both the domain log and the legacy history, so windows before the
 * switchover report the same way.
 *
 * - Points approved: deliverables the client (or the manager for the client) approved in the window.
 * - Proofs: moves into internal review (to Camila) and into client review; repeats within a minute count once. The
 *   legacy board reopened cards for a follow-up or email task and moved them back when it was done; a move in the
 *   same minute as such a task does not count.
 * - Revisions: proofs of a deliverable that had already been sent to the same reviewer before. Rework = revisions ÷ proofs.
 * - Points closed: deliverables closed as completed in the window.
 */
class TeamReportQuery
{
    private const TO_REVIEWER = [DeliverableStatus::INTERNAL_REVIEW->value, 'ENVIADO A CAMILA'];

    private const TO_CLIENT = [DeliverableStatus::CLIENT_REVIEW->value, 'ENVIADO AL CLIENTE'];

    /** Event types that record a status move: the domain log and the legacy board. */
    private const MOVE_EVENTS = ['deliverable_moved', 'CORE_STATUS_CHANGED', 'ORDER_SENT_TO_CLIENT', 'AUTO_RETURNED_AFTER_SUBTASK_COMPLETION'];

    /** Legacy task types that are coordination, not design: completing one moved the card back without a new proof. */
    private const COORDINATION_TASK_TYPES = [
        'FOLLOW UP CLIENTE', 'FOLLOW UP CAMILA', 'ENVIAR CORREO DE BIENVENIDA', 'ENVIAR CORREO DE ATRASO', 'SOLICITAR INFORMACIÓN',
    ];

    private const DESIGN_QUEUE = [
        DeliverableStatus::READY, DeliverableStatus::IN_DESIGN, DeliverableStatus::INTERNAL_REVIEW, DeliverableStatus::CLIENT_REVIEW,
    ];

    public const ATTENTION_LIMIT = 12;

    public function run(ReportPeriod $period, ?CarbonInterface $now = null): TeamReport
    {
        $now = CarbonImmutable::instance($now ?? now());
        $start = $period->start;
        $end = $period->end();

        $approved = $this->approvals($start, $end);
        $previousApproved = $this->approvals($period->previous()->start, $start);
        $toReviewer = $this->proofs(self::TO_REVIEWER, $start, $end);
        $toClient = $this->proofs(self::TO_CLIENT, $start, $end);
        $revisions = $toReviewer->concat($toClient)->where('revision', true)->values();
        $tasks = $this->completedTasks($start, $end);
        $previousTasks = $this->completedTasks($period->previous()->start, $start);

        $info = $this->deliverableInfo([
            ...$approved->pluck('deliverable_id'), ...$previousApproved->pluck('deliverable_id'),
            ...$toReviewer->pluck('order_id'), ...$toClient->pluck('order_id'), ...$tasks->pluck('order_id'), ...$previousTasks->pluck('order_id'),
        ]);

        $closed = Deliverable::query()->inStatus(DeliverableStatus::DONE)
            ->where('close_reason', CloseReason::COMPLETED->value)
            ->where('closed_at', '>=', $start->toDateTimeString())->where('closed_at', '<', $end->toDateTimeString())
            ->get(['id', 'assignee_id', 'points']);

        $open = $this->openWork()->inStatus(self::DESIGN_QUEUE)->get(['id', 'assignee_id', 'points', 'due_target', 'priority']);
        $remaining = $this->remainingPoints($open->modelKeys());
        $remainingOf = fn (Collection $deliverables): int => (int) $deliverables->sum(fn (Deliverable $deliverable): int => $remaining[$deliverable->id] ?? 0);
        $today = $now->startOfDay();
        $isOverdue = fn (Deliverable $deliverable): bool => $deliverable->due_target !== null && $deliverable->due_target->lessThan($today);
        $isHigh = fn (Deliverable $deliverable): bool => ($deliverable->priority ?? Priority::MEDIUM)->rank() > Priority::MEDIUM->rank();

        $pointsOf = fn (iterable $ids): int => collect($ids)->sum(fn (int $id): int => (int) ($info[$id]->points ?? 0));
        $byAssignee = fn (Collection $rows, string $idColumn): Collection => $rows->groupBy(fn (object $row): int => (int) ($info[$row->{$idColumn}]->assignee_id ?? 0));

        $approvedBy = $byAssignee($approved, 'deliverable_id');
        $previousBy = $byAssignee($previousApproved, 'deliverable_id');
        $toReviewerBy = $byAssignee($toReviewer, 'order_id');
        $toClientBy = $byAssignee($toClient, 'order_id');
        $revisionsBy = $byAssignee($revisions, 'order_id');
        $tasksBy = $byAssignee($tasks, 'order_id');
        $previousTasksBy = $byAssignee($previousTasks, 'order_id');
        $closedBy = $closed->groupBy(fn (Deliverable $deliverable): int => (int) $deliverable->assignee_id);
        $openBy = $open->groupBy(fn (Deliverable $deliverable): int => (int) $deliverable->assignee_id);

        $designers = User::query()->designTeam()->orderBy('name')->get()->map(function (User $designer) use (
            $period, $approvedBy, $previousBy, $toReviewerBy, $toClientBy, $revisionsBy, $tasksBy, $previousTasksBy, $closedBy, $openBy, $pointsOf, $remainingOf, $isOverdue, $isHigh,
        ): array {
            $id = $designer->id;
            $approvedRows = $approvedBy->get($id, collect());
            $doneTasks = $tasksBy->get($id, collect());
            $proofs = $toReviewerBy->get($id, collect())->count() + $toClientBy->get($id, collect())->count();
            $revisionCount = $revisionsBy->get($id, collect())->count();
            $mine = $openBy->get($id, collect());

            return [
                'designer' => $designer,
                'points_done' => (int) $doneTasks->sum('points'),
                'previous_points_done' => (int) $previousTasksBy->get($id, collect())->sum('points'),
                'weekly_points_done' => array_map(
                    fn (CarbonImmutable $week): int => (int) $doneTasks
                        ->filter(fn (object $task): bool => $task->completed_at >= $week->toDateTimeString() && $task->completed_at < $week->addWeek()->toDateTimeString())
                        ->sum('points'),
                    $period->weekStarts(),
                ),
                'approved_points' => $pointsOf($approvedRows->pluck('deliverable_id')),
                'approved_count' => $approvedRows->count(),
                'previous_approved_points' => $pointsOf($previousBy->get($id, collect())->pluck('deliverable_id')),
                'proofs_to_reviewer' => $toReviewerBy->get($id, collect())->count(),
                'proofs_to_client' => $toClientBy->get($id, collect())->count(),
                'revisions' => $revisionCount,
                'rework_pct' => $this->percent($revisionCount, $proofs),
                'tasks_done' => $doneTasks->count(),
                'closed_points' => (int) $closedBy->get($id, collect())->sum('points'),
                'closed_count' => $closedBy->get($id, collect())->count(),
                'open_points' => $remainingOf($mine),
                'open_count' => $mine->count(),
                'overdue_count' => $mine->filter($isOverdue)->count(),
                'high_priority_open' => $mine->filter($isHigh)->count(),
            ];
        })->filter(fn (array $row): bool => $row['points_done'] + $row['previous_points_done'] + $row['approved_points'] + $row['previous_approved_points'] + $row['proofs_to_reviewer']
            + $row['proofs_to_client'] + $row['revisions'] + $row['tasks_done'] + $row['closed_count'] + $row['open_count'] > 0)
            ->values()->all();

        $created = DB::table('orders')->whereNull('deleted_at')->whereNotNull('status')->where('in_backlog', false)
            ->where('created_at', '>=', $start->toDateTimeString())->where('created_at', '<', $end->toDateTimeString())
            ->get(['points']);
        $proofCount = $toReviewer->count() + $toClient->count();

        $team = [
            'points_done' => (int) $tasks->sum('points'),
            'previous_points_done' => (int) $previousTasks->sum('points'),
            'approved_points' => $pointsOf($approved->pluck('deliverable_id')),
            'approved_count' => $approved->count(),
            'previous_approved_points' => $pointsOf($previousApproved->pluck('deliverable_id')),
            'proofs' => $proofCount,
            'revisions' => $revisions->count(),
            'rework_pct' => $this->percent($revisions->count(), $proofCount),
            'tasks_done' => $tasks->count(),
            'closed_points' => (int) $closed->sum('points'),
            'closed_count' => $closed->count(),
            'new_points' => (int) $created->sum('points'),
            'new_count' => $created->count(),
            'open_points' => $remainingOf($open),
            'open_count' => $open->count(),
            'overdue_count' => $open->filter($isOverdue)->count(),
        ];

        $everyOpen = $this->openWork()->get(['id', 'priority']);
        $everyRemaining = $this->remainingPoints($everyOpen->modelKeys());
        $priorityMix = array_map(function (Priority $priority) use ($everyOpen, $everyRemaining): array {
            $matching = $everyOpen->filter(fn (Deliverable $deliverable): bool => ($deliverable->priority ?? Priority::MEDIUM) === $priority);

            return [
                'priority' => $priority,
                'count' => $matching->count(),
                'points' => (int) $matching->sum(fn (Deliverable $deliverable): int => $everyRemaining[$deliverable->id] ?? 0),
            ];
        }, Priority::descending());

        $attention = $this->openWork()
            ->inStatus(self::DESIGN_QUEUE)
            ->where(fn (Builder $query) => $query
                ->whereDate('due_target', '<', $today->toDateString())
                ->orWhereIn('priority', [Priority::HIGH->value, Priority::HIGHEST->value]))
            ->with(['workOrder.client', 'assignee'])
            ->orderByRaw("CASE priority WHEN 'highest' THEN 5 WHEN 'high' THEN 4 WHEN 'medium' THEN 3 WHEN 'low' THEN 2 ELSE 1 END DESC")
            ->orderByRaw('due_target IS NULL')
            ->orderBy('due_target')
            ->orderBy('id')
            ->limit(self::ATTENTION_LIMIT)
            ->get();

        return new TeamReport($period, $now, $designers, $team, $priorityMix, $attention);
    }

    /**
     * Open work outside the backlog that is not on hold.
     *
     * @return Builder<Deliverable>
     */
    private function openWork(): Builder
    {
        return Deliverable::query()->active()->inWorkspace()->whereDoesntHave('holds', fn (Builder $holds) => $holds->whereNull('ended_at'));
    }

    /**
     * Tasks completed in the window, with their points.
     *
     * @return Collection<int, object{order_id: int, points: ?int, completed_at: string}>
     */
    private function completedTasks(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('related_tasks')->whereNull('deleted_at')
            ->where('completed_at', '>=', $from->toDateTimeString())->where('completed_at', '<', $to->toDateTimeString())
            ->get(['order_id', 'points', 'completed_at']);
    }

    /**
     * Points of the tasks still to do, per deliverable.
     *
     * @param  list<int>  $deliverableIds
     * @return array<int, int>
     */
    private function remainingPoints(array $deliverableIds): array
    {
        $remaining = [];

        foreach (array_chunk($deliverableIds, 500) as $ids) {
            $rows = DB::table('related_tasks')->whereIn('order_id', $ids)->whereNull('deleted_at')
                ->where('status', '!=', 'done')->groupBy('order_id')->selectRaw('order_id, SUM(points) as points')->get();

            foreach ($rows as $row) {
                $remaining[(int) $row->order_id] = (int) $row->points;
            }
        }

        return $remaining;
    }

    /**
     * One row per deliverable approved for the client in the window (first approval in it).
     *
     * @return Collection<int, object{deliverable_id: int, approved_at: string}>
     */
    private function approvals(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('approvals')
            ->whereIn('source', ['client', 'manager'])
            ->where('approved_at', '>=', $from->toDateTimeString())->where('approved_at', '<', $to->toDateTimeString())
            ->groupBy('deliverable_id')
            ->selectRaw('deliverable_id, MIN(approved_at) as approved_at')
            ->get();
    }

    /**
     * Proofs sent in the window: moves into the given review statuses, one per deliverable and minute, each marked as a
     * revision when the deliverable had been sent to the same review before.
     *
     * @param  list<string>  $into
     * @return Collection<int, object{order_id: int, minute: string, revision: bool}>
     */
    private function proofs(array $into, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $sends = fn () => DB::table('order_events')
            ->whereIn('event_type', self::MOVE_EVENTS)
            ->whereIn('new_value', $into)
            ->where(fn ($query) => $query->whereNull('previous_value')->orWhereNotIn('previous_value', $into))
            ->where('created_at', '<', $end->toDateTimeString())
            ->whereNotExists(fn ($tasks) => $tasks->select(DB::raw(1))->from('related_tasks')
                ->whereColumn('related_tasks.order_id', 'order_events.order_id')
                ->whereIn('related_tasks.type', self::COORDINATION_TASK_TYPES)
                ->whereRaw('substr(related_tasks.completed_at, 1, 16) = substr(order_events.created_at, 1, 16)'))
            ->selectRaw('order_id, substr(created_at, 1, 16) as minute')
            ->distinct();

        $inWindow = $sends()->where('created_at', '>=', $start->toDateTimeString())->orderBy('minute')->get();

        if ($inWindow->isEmpty()) {
            return collect();
        }

        $firstSend = collect(array_chunk($inWindow->pluck('order_id')->unique()->values()->all(), 500))
            ->flatMap(fn (array $ids) => $sends()->whereIn('order_id', $ids)->get())
            ->groupBy('order_id')
            ->map(fn (Collection $rows): string => (string) $rows->min('minute'));

        return $inWindow->map(fn (object $row): object => (object) [
            'order_id' => (int) $row->order_id,
            'minute' => $row->minute,
            'revision' => $row->minute > ($firstSend[$row->order_id] ?? $row->minute),
        ]);
    }

    /**
     * @param  list<int|string>  $ids
     * @return Collection<int, object{id: int, assignee_id: ?int, points: ?int}>
     */
    private function deliverableInfo(array $ids): Collection
    {
        return collect(array_chunk(array_values(array_unique(array_map('intval', $ids))), 500))
            ->flatMap(fn (array $chunk) => DB::table('orders')->whereIn('id', $chunk)->get(['id', 'assignee_id', 'points']))
            ->keyBy('id');
    }

    private function percent(int $part, int $whole): ?int
    {
        return $whole === 0 ? null : (int) round($part / $whole * 100);
    }
}
