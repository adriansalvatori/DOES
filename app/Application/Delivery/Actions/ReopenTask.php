<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reopens a task. When it was the gate that advanced the deliverable and the deliverable is still in the status it
 * advanced to, the move is reversed. If the deliverable went further, an alert asks someone to check it.
 */
class ReopenTask
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow, private DeliveryLog $log) {}

    public function __invoke(Task $task, ?User $actor = null): Task
    {
        $deliverable = $task->deliverable;
        $this->authorizeActor($actor, 'manageTasks', $deliverable);

        if (! $task->isDone()) {
            return $task;
        }

        return DB::transaction(function () use ($task, $deliverable, $actor): Task {
            $task->update(['status' => 'todo', 'completed_at' => null]);
            $this->log->record($deliverable, 'task_reopened', $actor, null, $task->title, ['task_id' => $task->getKey()]);

            if ($task->isGate() && $task->required && $task->advances_to !== null) {
                $this->undoAdvance($task, $actor);
            }

            return $task;
        });
    }

    private function undoAdvance(Task $task, ?User $actor): void
    {
        $deliverable = $task->deliverable->refresh();

        if ($deliverable->status === $task->advances_to) {
            $lastMove = $deliverable->events()->where('event_type', 'deliverable_moved')->latest('id')->first();

            if (($lastMove?->metadata['task_id'] ?? null) === $task->getKey()) {
                $this->workflow->transition($deliverable, $deliverable->status->previous(), TransitionReason::TASK_COMPLETED, $actor, [
                    'task_id' => $task->getKey(),
                    'reversal' => true,
                ]);

                return;
            }
        }

        if ($deliverable->status->position() >= $task->advances_to->position()) {
            Alert::create([
                'deliverable_id' => $deliverable->getKey(),
                'kind' => AlertKind::TASK_REOPENED,
                'due_on' => now()->toDateString(),
                'user_id' => $deliverable->assignee_id,
            ]);
        }
    }
}
