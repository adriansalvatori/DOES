<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\PlanWeek;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Scheduling\WipPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Plan week: each card is a task, placed by its day and its person (the task's own, else the deliverable's designer).
 * Done tasks stay on their day. Unfinished tasks from earlier weeks show on today while you look at this week.
 * Planned deliverables with no open task show as their own card so they never disappear. The backlog lists work to
 * plan by promised date; blocked work is listed apart and never plannable. WIP is a soft limit: cells only count it.
 */
class PlanWeekQuery
{
    public const BLOCKED_BACKLOG_CAP = 30;

    public const SORT_MANUAL = 'manual';

    public const SORT_PRIORITY = 'priority';

    private const DELIVERABLE = ['workOrder.client', 'workOrder.clientLocation', 'assignee', 'openBlock', 'openHold'];

    public function __construct(private WipPolicy $wip) {}

    public function run(CarbonInterface $anyDayOfWeek, string $sort = self::SORT_MANUAL, ?CarbonInterface $today = null): PlanWeek
    {
        $monday = CarbonImmutable::instance($anyDayOfWeek)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $days = array_map(fn (int $offset): CarbonImmutable => $monday->addDays($offset), range(0, 4));
        $nextMonday = $monday->addWeek();
        $nextFriday = $nextMonday->addDays(4);
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        $carryDay = $today->betweenIncluded($monday, $monday->addDays(6)) ? ($today->lte($days[4]) ? $today : $days[4]) : null;

        $tasks = $this->tasks($monday, $nextFriday, $carryDay !== null);
        $carried = $tasks->filter(fn (Task $task): bool => $task->scheduled_date->lt($monday))->modelKeys();
        $bare = $this->plannedWithoutOpenTasks($monday, $nextFriday);

        $columnOf = function (CarbonInterface $date) use ($monday, $nextMonday, $carryDay): string {
            if ($date->lt($monday)) {
                return $carryDay->toDateString();
            }

            return $date->gte($nextMonday) ? PlanWeek::NEXT_WEEK : $date->toDateString();
        };

        $people = $this->people($tasks, $bare);
        $columns = [...array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $days), PlanWeek::NEXT_WEEK];
        $rows = [];

        foreach ($people as $person) {
            $personId = $person?->getKey();
            $cells = [];

            foreach ($columns as $column) {
                $cellTasks = $tasks
                    ->filter(fn (Task $task): bool => $this->personOf($task) === $personId && $columnOf($task->scheduled_date) === $column)
                    ->values();
                $cellDeliverables = $bare
                    ->filter(fn (Deliverable $deliverable): bool => $deliverable->assignee_id === $personId && $columnOf($deliverable->planned_for) === $column)
                    ->values();

                $cells[$column] = [
                    'tasks' => $this->sorted($cellTasks, $sort),
                    'deliverables' => $cellDeliverables,
                    'points' => (int) $cellTasks->sum('points'),
                    'open' => $cellTasks->reject(fn (Task $task): bool => $task->isDone())->count(),
                    'used' => $cellTasks->toBase()
                        ->reject(fn (Task $task): bool => $task->isDone() || $task->deliverable->openBlock !== null)
                        ->pluck('order_id')
                        ->merge($cellDeliverables->filter(fn (Deliverable $deliverable): bool => $deliverable->openBlock === null)->modelKeys())
                        ->unique()->count(),
                ];
            }

            $rows[] = ['assignee' => $person, 'limit' => $person !== null ? $this->wip->limitFor($person) : 0, 'cells' => $cells];
        }

        return new PlanWeek($monday, $days, $nextMonday, $this->backlog(), $rows, $this->blockedBacklog(), array_values($carried));
    }

    /**
     * @return Collection<int, Task>
     */
    private function tasks(CarbonImmutable $from, CarbonImmutable $to, bool $withCarryOver): Collection
    {
        return Task::query()
            ->whereHas('deliverable', fn (Builder $deliverables) => $deliverables->inWorkspace())
            ->where(fn (Builder $when) => $when
                ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
                ->when($withCarryOver, fn (Builder $late) => $late->orWhere(fn (Builder $carried) => $carried
                    ->where('scheduled_date', '<', $from->toDateString())
                    ->where('status', '!=', 'done')
                    ->whereHas('deliverable', fn (Builder $deliverables) => $deliverables->active()))))
            ->with(['assignee', ...array_map(fn (string $relation): string => 'deliverable.'.$relation, self::DELIVERABLE)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Planned deliverables with a person and no open task: shown as a card of their own until a task is added.
     *
     * @return Collection<int, Deliverable>
     */
    private function plannedWithoutOpenTasks(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Deliverable::query()
            ->inWorkspace()
            ->inStatus([DeliverableStatus::READY, DeliverableStatus::IN_DESIGN])
            ->whereNotNull('assignee_id')
            ->whereBetween('planned_for', [$from->toDateString(), $to->toDateString()])
            ->whereDoesntHave('tasks', fn (Builder $tasks) => $tasks->where('status', '!=', 'done'))
            ->with(self::DELIVERABLE)
            ->orderBy('id')
            ->get();
    }

    /**
     * The design team in name order, then anyone else who has work this week, then "Unassigned" (null) if needed.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, Deliverable>  $bare
     * @return list<?User>
     */
    private function people(Collection $tasks, Collection $bare): array
    {
        $team = User::query()->designTeam()->orderBy('name')->get();
        $busy = $tasks->toBase()->map(fn (Task $task): ?int => $this->personOf($task))->merge($bare->toBase()->pluck('assignee_id'))->unique();
        $others = User::query()->whereKey($busy->filter()->diff($team->modelKeys())->values())->orderBy('name')->get();

        return [...$team->all(), ...$others->all(), ...($busy->contains(null) ? [null] : [])];
    }

    private function personOf(Task $task): ?int
    {
        return $task->assignee_user_id ?? $task->deliverable?->assignee_id;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    private function sorted(Collection $tasks, string $sort): Collection
    {
        if ($sort !== self::SORT_PRIORITY) {
            return $tasks;
        }

        return $tasks->sortBy([
            fn (Task $a, Task $b): int => ($b->deliverable->priority?->rank() ?? 3) <=> ($a->deliverable->priority?->rank() ?? 3),
            fn (Task $a, Task $b): int => [(int) $a->sort_order, $a->id] <=> [(int) $b->sort_order, $b->id],
        ])->values();
    }

    /**
     * Ready work nobody planned, plus open work with tasks that have no day, by work order promised date.
     *
     * @return Collection<int, Deliverable>
     */
    private function backlog(): Collection
    {
        $unscheduled = fn (Builder $tasks) => $tasks->where('status', '!=', 'done')->whereNull('scheduled_date');

        return Deliverable::query()
            ->inWorkspace()
            ->active()
            ->notBlocked()
            ->where(fn (Builder $either) => $either
                ->where(fn (Builder $ready) => $ready->inStatus(DeliverableStatus::READY)->whereNull('planned_for'))
                ->orWhereHas('tasks', $unscheduled))
            ->leftJoin('work_orders', 'work_orders.id', '=', 'orders.work_order_id')
            ->select('orders.*')
            ->withCount(['tasks as unscheduled_tasks_count' => $unscheduled])
            ->orderByRaw('work_orders.promised_date IS NULL')
            ->orderBy('work_orders.promised_date')
            ->orderBy('orders.id')
            ->with(self::DELIVERABLE)
            ->get();
    }

    /**
     * @return Collection<int, Deliverable>
     */
    private function blockedBacklog(): Collection
    {
        return Deliverable::query()
            ->inWorkspace()
            ->inStatus(DeliverableStatus::READY)
            ->whereNull('planned_for')
            ->blocked()
            ->with(self::DELIVERABLE)
            ->orderBy('id')
            ->limit(self::BLOCKED_BACKLOG_CAP)
            ->get();
    }
}
