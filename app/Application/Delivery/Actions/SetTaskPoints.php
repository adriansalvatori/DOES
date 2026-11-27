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
 * Sets the Fibonacci points of a task (0 to 13, empty to clear). The deliverable's points follow as the sum of its tasks.
 */
class SetTaskPoints
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log) {}

    /**
     * @return bool Whether the points changed.
     */
    public function __invoke(Task $task, ?int $points, ?User $actor = null): bool
    {
        $deliverable = $task->deliverable;
        $this->authorizeActor($actor, 'manageTasks', $deliverable);

        if ($points !== null && ! in_array($points, PointsEstimator::TASK_SCALE, true)) {
            throw WorkflowException::refused('Points must be a Fibonacci number: 0, 1, 2, 3, 5, 8 or 13.');
        }

        if ($task->points === $points) {
            return false;
        }

        DB::transaction(function () use ($task, $points, $deliverable, $actor): void {
            $previous = $task->points;
            $task->update(['points' => $points]);

            $this->log->record($deliverable, 'task_points_changed', $actor, $previous === null ? null : (string) $previous, $points === null ? null : (string) $points, [
                'task_id' => $task->getKey(),
            ]);
        });

        return true;
    }
}
