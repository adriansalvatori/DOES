<?php

namespace App\Livewire\Tasks;

use App\Enums\RelatedTaskType;
use App\Models\RelatedTask;
use Livewire\Component;

class TaskList extends Component
{
    public $search = '';

    public $statusFilter = 'todo';

    public $typeFilter = 'all';

    public function toggleTaskStatus($taskId)
    {
        $task = RelatedTask::findOrFail($taskId);
        $newStatus = $task->status === 'done' ? 'todo' : 'done';

        $task->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'done' ? now() : null,
        ]);

        session()->flash('message', "Estado de tarea '{$task->title}' actualizado.");
    }

    public function render()
    {
        $query = RelatedTask::whereHas('order', fn ($q) => $q->inWorkspace())->with(['order', 'assignee']);

        if (! empty($this->search)) {
            $query->search($this->search);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
        }

        $tasks = $query->orderBy('due_date', 'asc')->get();

        return view('livewire.tasks.task-list', [
            'tasks' => $tasks,
            'taskTypes' => RelatedTaskType::cases(),
        ])->layout('components.layouts.app', ['title' => 'Tareas Vinculadas - Kudos Design Ops']);
    }
}
