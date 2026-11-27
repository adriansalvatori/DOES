<?php

namespace App\Application\Delivery\Actions;

use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Plans tasks on a person's day (Plan week). The list is the day's full order: each task gets its position, so the
 * same call moves, reassigns and reorders. Every call returns the previous state of the tasks it changed, which
 * restore() puts back (undo). Managers plan anyone; a designer only their own row (TaskPolicy::plan).
 */
class ScheduleTasks
{
    public function __construct(private DeliveryLog $log) {}

    /**
     * @param  list<int>  $orderedTaskIds  The tasks of the day, in order.
     * @param  ?CarbonInterface  $day  Null takes the tasks off the plan (they keep their person).
     * @return list<array{id: int, assignee_user_id: ?int, scheduled_date: ?string, sort_order: int}>
     */
    public function __invoke(array $orderedTaskIds, ?User $assignee, ?CarbonInterface $day, ?User $actor = null): array
    {
        $tasks = Task::query()->with('deliverable')->whereKey($orderedTaskIds)->get()->keyBy('id');
        $date = $day?->toDateString();
        $changes = [];

        foreach (array_values(array_unique($orderedTaskIds)) as $position => $taskId) {
            $task = $tasks->get($taskId);

            if ($task === null) {
                continue;
            }

            $newAssigneeId = $day === null ? $task->assignee_user_id : $assignee?->getKey();
            $moves = $task->scheduled_date?->toDateString() !== $date || ($day !== null && $task->effectiveAssigneeId() !== $newAssigneeId);

            if ($actor !== null) {
                Gate::forUser($actor)->authorize('plan', [$task, $moves && $day !== null ? $assignee : null]);
            }

            if ($moves || (int) $task->sort_order !== $position || $task->assignee_user_id !== $newAssigneeId) {
                $changes[] = [$task, ['assignee_user_id' => $newAssigneeId, 'scheduled_date' => $date, 'sort_order' => $position], $moves];
            }
        }

        return $this->apply($changes, $actor, 'planned');
    }

    /**
     * Puts tasks back the way a previous call found them.
     *
     * @param  list<array{id: int, assignee_user_id: ?int, scheduled_date: ?string, sort_order: int}>  $previous
     * @return list<array{id: int, assignee_user_id: ?int, scheduled_date: ?string, sort_order: int}>
     */
    public function restore(array $previous, ?User $actor = null): array
    {
        $tasks = Task::query()->with('deliverable')->whereKey(array_column($previous, 'id'))->get()->keyBy('id');
        $people = User::query()->whereKey(array_filter(array_column($previous, 'assignee_user_id')))->get()->keyBy('id');
        $changes = [];

        foreach ($previous as $state) {
            $task = $tasks->get($state['id']);

            if ($task === null) {
                continue;
            }

            if ($actor !== null) {
                Gate::forUser($actor)->authorize('plan', [$task, $people->get($state['assignee_user_id'])]);
            }

            $changes[] = [$task, ['assignee_user_id' => $state['assignee_user_id'], 'scheduled_date' => $state['scheduled_date'], 'sort_order' => (int) $state['sort_order']], true];
        }

        return $this->apply($changes, $actor, 'undone');
    }

    /**
     * @param  list<array{0: Task, 1: array{assignee_user_id: ?int, scheduled_date: ?string, sort_order: int}, 2: bool}>  $changes
     * @return list<array{id: int, assignee_user_id: ?int, scheduled_date: ?string, sort_order: int}>
     */
    private function apply(array $changes, ?User $actor, string $how): array
    {
        return DB::transaction(function () use ($changes, $actor, $how): array {
            $previous = [];

            foreach ($changes as [$task, $values, $moves]) {
                $previous[] = [
                    'id' => $task->getKey(),
                    'assignee_user_id' => $task->assignee_user_id,
                    'scheduled_date' => $task->scheduled_date?->toDateString(),
                    'sort_order' => (int) $task->sort_order,
                ];
                $from = $task->scheduled_date?->toDateString();
                $task->forceFill($values)->save();

                if ($moves && $task->deliverable !== null) {
                    $this->log->record($task->deliverable, 'task_scheduled', $actor, $from, $values['scheduled_date'], [
                        'task_id' => $task->getKey(),
                        'assignee_user_id' => $values['assignee_user_id'],
                        'how' => $how,
                    ]);
                }
            }

            return $previous;
        });
    }
}
