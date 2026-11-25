<?php

namespace App\Livewire\Today;

use App\Application\Delivery\Actions\CompleteTask;
use App\Application\Delivery\Actions\ReopenTask;
use App\Application\Delivery\Actions\ResolveAlert;
use App\Application\Delivery\Queries\TodayQuery;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * What a designer can act on today: planned work, carry-overs, overdue work and client follow-up alerts.
 * Checking the next gate task moves the card and offers an undo.
 */
class DesignerSection extends Component
{
    public int $designerId;

    public ?int $undoTaskId = null;

    public string $undoMessage = '';

    public function mount(int $designerId): void
    {
        $viewer = auth()->user();
        abort_unless($viewer !== null && ($viewer->getKey() === $designerId || Roles::isManager($viewer)), 403);
        $this->designerId = $designerId;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function data(): array
    {
        return app(TodayQuery::class)->forDesigner(User::query()->findOrFail($this->designerId));
    }

    /**
     * The first unfinished required gate task that leads to the next status, keyed by deliverable id.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function nextGates(): Collection
    {
        $deliverables = collect([$this->data['plannedToday'], $this->data['carryOvers'], $this->data['overdue']])->flatten(1)->keyBy('id');

        if ($deliverables->isEmpty()) {
            return collect();
        }

        return Task::query()
            ->whereIn('order_id', $deliverables->keys())
            ->where('required', true)
            ->where('status', '!=', 'done')
            ->whereIn('kind', [TaskKind::GATE_SEND_PROOF->value, TaskKind::GATE_PREPARE_PRODUCTION_FILES->value, TaskKind::GATE_CUSTOM->value])
            ->orderBy('id')
            ->get()
            ->filter(fn (Task $task): bool => $task->advances_to === $deliverables[$task->order_id]->status->next())
            ->unique('order_id')
            ->keyBy('order_id');
    }

    public function completeTask(int $taskId): void
    {
        $task = Task::query()->with('deliverable')->findOrFail($taskId);
        $this->guardOwnership($task->deliverable);

        $this->attempt(function () use ($task): void {
            app(CompleteTask::class)($task, auth()->user());
            $this->undoTaskId = $task->getKey();
            $this->undoMessage = __('Completed: :task', ['task' => $task->title]);
        });
    }

    public function undoTask(): void
    {
        if ($this->undoTaskId === null) {
            return;
        }
        $task = Task::query()->with('deliverable')->findOrFail($this->undoTaskId);
        $this->guardOwnership($task->deliverable);

        $this->attempt(fn () => app(ReopenTask::class)($task, auth()->user()));
        $this->dismissUndo();
    }

    public function dismissUndo(): void
    {
        $this->undoTaskId = null;
        $this->undoMessage = '';
    }

    public function logFollowUp(int $alertId): void
    {
        $alert = Alert::query()->open()->where('user_id', $this->designerId)->findOrFail($alertId);

        $this->attempt(fn () => app(ResolveAlert::class)($alert, auth()->user()));
    }

    private function guardOwnership(Deliverable $deliverable): void
    {
        abort_unless($deliverable->assignee_id === $this->designerId, 403);
    }

    private function attempt(callable $useCase): void
    {
        try {
            $useCase();
        } catch (WorkflowException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        } catch (AuthorizationException) {
            $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');
        }

        unset($this->data, $this->nextGates);
    }

    /**
     * The panel changed a deliverable: reload this section only.
     */
    #[On('deliverable-changed')]
    public function refreshAfterPanelChange(): void
    {
        unset($this->data, $this->nextGates);
    }

    public function render()
    {
        return view('livewire.today.designer-section');
    }
}
