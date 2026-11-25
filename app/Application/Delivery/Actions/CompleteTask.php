<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Completes a task. Completing the last required gate of the current status advances the deliverable; the gate is
 * identified by its data, never by its title. Steps with their own exit rule (review, client approval, receipt)
 * are not advanced by tasks.
 */
class CompleteTask
{
    use AuthorizesActor;

    /**
     * Statuses whose exit is controlled by an action, not by tasks.
     *
     * @var list<DeliverableStatus>
     */
    public const ACTION_CONTROLLED = [
        DeliverableStatus::INTERNAL_REVIEW,
        DeliverableStatus::CLIENT_REVIEW,
        DeliverableStatus::FULFILLMENT,
    ];

    public function __construct(private DeliverableWorkflow $workflow, private DeliveryLog $log) {}

    public function __invoke(Task $task, ?User $actor = null): Task
    {
        $deliverable = $task->deliverable;
        $this->authorizeActor($actor, 'manageTasks', $deliverable);

        if ($task->isDone()) {
            return $task;
        }

        return DB::transaction(function () use ($task, $deliverable, $actor): Task {
            $task->update(['status' => 'done', 'completed_at' => now()]);
            $this->log->record($deliverable, 'task_completed', $actor, null, $task->title, ['task_id' => $task->getKey()]);

            $this->advanceIfLastGate($task, $actor);

            return $task;
        });
    }

    private function advanceIfLastGate(Task $task, ?User $actor): void
    {
        if (! $task->isGate() || ! $task->required || $task->advances_to === null) {
            return;
        }

        $deliverable = $task->deliverable->refresh();
        $from = $deliverable->status;

        if ($from->next() !== $task->advances_to || in_array($from, self::ACTION_CONTROLLED, true)) {
            return;
        }

        if ($deliverable->isBlocked() || $deliverable->isOnHold()) {
            return;
        }

        if ($this->workflow->requiredGates($deliverable, $task->advances_to)->contains(fn (Task $gate): bool => ! $gate->isDone())) {
            return;
        }

        $this->workflow->transition($deliverable, $task->advances_to, TransitionReason::TASK_COMPLETED, $actor, ['task_id' => $task->getKey()]);
    }
}
