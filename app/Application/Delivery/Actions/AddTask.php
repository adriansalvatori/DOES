<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Adds a task. A gate task is required and names the status it advances the deliverable to; a work task is a plain to-do.
 */
class AddTask
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log) {}

    public function __invoke(
        Deliverable $deliverable,
        string $title,
        TaskKind $kind = TaskKind::WORK,
        ?DeliverableStatus $advancesTo = null,
        ?CarbonInterface $dueDate = null,
        ?User $actor = null,
    ): Task {
        $this->authorizeActor($actor, 'manageTasks', $deliverable);

        $isGate = $kind !== TaskKind::WORK;
        if ($isGate && $advancesTo === null) {
            throw WorkflowException::refused('A gate task must say which status it advances to.');
        }

        $task = $deliverable->tasks()->create([
            'title' => trim($title),
            'status' => 'todo',
            'kind' => $kind,
            'advances_to' => $isGate ? $advancesTo : null,
            'required' => $isGate,
            'due_date' => $dueDate?->toDateString(),
        ]);

        $this->log->record($deliverable, 'task_added', $actor, null, $task->title, ['task_id' => $task->getKey(), 'kind' => $kind->value]);

        return $task;
    }
}
