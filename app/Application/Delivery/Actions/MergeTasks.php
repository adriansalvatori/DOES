<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\PointsEstimator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Merges tasks of one deliverable into one. The main task keeps its kind and gate and takes the chosen title, points,
 * due date and status; the others go to the trash marked as merged into it. Completing or reopening the main task
 * goes through the usual task actions, so a gate still moves the deliverable.
 */
class MergeTasks
{
    use AuthorizesActor;

    /** Fields whose value can be taken from any of the merged tasks; points may also be the sum of all of them. */
    public const FIELDS = ['title', 'points', 'due_date', 'status'];

    public function __construct(private DeliveryLog $log, private CompleteTask $complete, private ReopenTask $reopen) {}

    /**
     * @param  list<Task>  $others
     * @param  array<string, mixed>  $fields  Chosen values by field (see FIELDS); missing fields keep the main value.
     */
    public function __invoke(Task $main, array $others, array $fields = [], ?User $actor = null): Task
    {
        $deliverable = $main->deliverable;
        $this->authorizeActor($actor, 'manageTasks', $deliverable);

        $others = collect($others)->reject(fn (Task $task): bool => $task->is($main))->unique('id')->values();
        if ($others->isEmpty()) {
            throw WorkflowException::refused('Choose at least two tasks to merge.');
        }
        if ($others->contains(fn (Task $task): bool => $task->order_id !== $main->order_id)) {
            throw WorkflowException::refused('Only tasks of the same deliverable can be merged.');
        }
        $sum = (int) $others->sum('points') + (int) $main->points;
        if (array_key_exists('points', $fields) && $fields['points'] !== null && ! in_array($fields['points'], PointsEstimator::TASK_SCALE, true)
            && $fields['points'] !== $sum) {
            throw WorkflowException::refused('Points must be a Fibonacci number: 0, 1, 2, 3, 5, 8 or 13.');
        }

        return DB::transaction(function () use ($main, $others, $fields, $actor, $deliverable): Task {
            $main->refresh();
            $status = $fields['status'] ?? $main->status;

            $main->fill(array_intersect_key($fields, array_flip(['title', 'points', 'due_date'])))->save();

            foreach ($others as $other) {
                $other->forceFill(['merged_into_id' => $main->getKey()])->save();
                $other->delete();
            }

            if ($status === 'done' && ! $main->isDone()) {
                ($this->complete)($main->fresh(), $actor);
            } elseif ($status !== 'done' && $main->isDone()) {
                ($this->reopen)($main->fresh(), $actor);
            }

            $this->log->record($deliverable, 'tasks_merged', $actor, null, $main->title, [
                'task_id' => $main->getKey(),
                'merged' => $others->map(fn (Task $other): array => ['id' => $other->getKey(), 'title' => $other->title, 'points' => $other->points])->all(),
            ]);

            return $main->fresh();
        });
    }
}
