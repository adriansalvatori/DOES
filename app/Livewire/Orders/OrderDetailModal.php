<?php

namespace App\Livewire\Orders;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Services\AutomationEngine;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderDetailModal extends Component
{
    public $orderId = null;
    public $showModal = false;
    public $showApprovalModal = false;
    public $showDelayModal = false;

    // Edit Mode state
    public $isEditing = false;
    public $editWoNumber = '';
    public $editCompanyName = '';
    public $editResponsiblePerson = '';
    public $editTaskName = '';
    public $editDesignerId = null;
    public $editCoreStatus = '';
    public $editSubstatus = '';
    public $editDueDate = '';
    public $editClientRevisionCount = 0;
    public $editInternalRevisionCount = 0;

    // Approval fields
    public $measuresConfirmed = true;
    public $estimateApproved = true;

    // Delay fields
    public $clientPromisedDate;
    public $delayReason = 'Correo de atraso enviado y nueva fecha acordada con cliente';

    // New task field
    public $newTaskTitle = '';

    public function mount($orderId = null)
    {
        $this->orderId = $orderId;
        $this->clientPromisedDate = now()->addDays(2)->toDateString();
    }

    #[On('open-order-detail')]
    public function openModal($orderId = null, $startEdit = false)
    {
        if ($orderId) {
            $this->orderId = $orderId;
        }
        $this->showModal = true;

        if ($startEdit) {
            $this->startEditing();
        } else {
            $this->isEditing = false;
        }
    }

    public function startEditing()
    {
        if (!$this->orderId) return;

        $order = Order::findOrFail($this->orderId);
        $this->editWoNumber = preg_replace('/^WO\s*/i', '', $order->wo_number ?? '');
        $this->editCompanyName = $order->company_name ?? '';
        $this->editResponsiblePerson = $order->responsible_person ?? '';
        $this->editTaskName = $order->task_name ?? '';
        $this->editDesignerId = $order->designer_id;
        $this->editCoreStatus = $order->core_status ? $order->core_status->value : CoreStatus::ENTRANTE->value;
        $this->editSubstatus = $order->substatus ? $order->substatus->value : '';
        $this->editDueDate = $order->current_due_date ? $order->current_due_date->toDateString() : '';
        $this->editClientRevisionCount = $order->client_revision_count ?? 0;
        $this->editInternalRevisionCount = $order->internal_revision_count ?? 0;

        $this->isEditing = true;
    }

    public function cancelEditing()
    {
        $this->isEditing = false;
    }

    public function saveOrder($addToWorkspace = false)
    {
        if (!$this->orderId) return;

        $order = Order::findOrFail($this->orderId);

        $cleanWo = trim(preg_replace('/^WO\s*/i', '', $this->editWoNumber ?? ''));

        $updateData = [
            'wo_number' => !empty($cleanWo) ? "WO {$cleanWo}" : null,
            'company_name' => $this->editCompanyName,
            'responsible_person' => !empty($this->editResponsiblePerson) ? $this->editResponsiblePerson : null,
            'task_name' => $this->editTaskName,
            'designer_id' => $this->editDesignerId ?: null,
            'core_status' => $this->editCoreStatus ?: $order->core_status,
            'substatus' => !empty($this->editSubstatus) ? $this->editSubstatus : null,
            'current_due_date' => !empty($this->editDueDate) ? $this->editDueDate : null,
            'client_revision_count' => (int)$this->editClientRevisionCount,
            'internal_revision_count' => (int)$this->editInternalRevisionCount,
        ];

        if ($addToWorkspace) {
            $updateData['in_workspace'] = true;
        }

        $order->update($updateData);

        // Trigger overdue task creation immediately if updated date is today or overdue
        app(\App\Services\AutomationEngine::class)->checkAndCreateOverdueTask($order->fresh());

        // Safely sync updated title to Trello if card is linked
        if ($order->trello_card_id) {
            app(\App\Services\TrelloSyncService::class)->updateCardOnTrello($order);
        }

        $this->isEditing = false;
        $this->dispatch('order-updated');

        if ($addToWorkspace) {
            session()->flash('message', "Orden {$order->company_name} actualizada y añadida al Workspace activo.");
        } else {
            session()->flash('message', "Orden {$order->company_name} actualizada correctamente.");
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showApprovalModal = false;
        $this->showDelayModal = false;
        $this->isEditing = false;
    }

    public function moveToBacklog()
    {
        if (!$this->orderId) return;

        $order = Order::findOrFail($this->orderId);
        $order->update(['in_workspace' => false]);
        $this->closeModal();
        $this->dispatch('order-updated');
        session()->flash('message', "Orden {$order->company_name} movida de regreso al Backlog.");
    }

    public function addToWorkspaceDirectly()
    {
        if (!$this->orderId) return;

        $order = Order::findOrFail($this->orderId);
        $order->update(['in_workspace' => true]);
        $this->dispatch('order-updated');
        session()->flash('message', "Orden {$order->company_name} añadida al Workspace activo.");
    }

    public function submitApproval()
    {
        if (!$this->orderId) return;

        $order = Order::findOrFail($this->orderId);
        
        app(AutomationEngine::class)->processApproval(
            $order,
            (bool)$this->measuresConfirmed,
            (bool)$this->estimateApproved
        );

        $this->closeModal();
        $this->dispatch('order-updated');
        session()->flash('message', "Aprobación procesada para {$order->company_name}.");
    }

    public function submitDelayResolution()
    {
        if (!$this->orderId) return;

        $order = Order::findOrFail($this->orderId);
        
        app(AutomationEngine::class)->resolveDelay(
            $order,
            Carbon::parse($this->clientPromisedDate),
            $this->delayReason
        );

        $this->showDelayModal = false;
        $this->dispatch('order-updated');
        session()->flash('message', "Atraso resuelto y nueva fecha fijada al {$this->clientPromisedDate}.");
    }

    public function addTask()
    {
        if (empty(trim($this->newTaskTitle)) || !$this->orderId) return;

        $order = Order::findOrFail($this->orderId);

        $order->relatedTasks()->create([
            'title' => $this->newTaskTitle,
            'type' => \App\Enums\RelatedTaskType::RESOLVER,
            'status' => 'todo',
            'assignee_id' => $order->designer_id,
            'due_date' => now()->toDateString(),
            'priority' => 'normal',
        ]);

        $this->newTaskTitle = '';
        $this->dispatch('order-updated');
    }

    public function deleteTask($taskId)
    {
        $task = \App\Models\RelatedTask::find($taskId);
        if ($task) {
            $taskName = $task->title;
            $task->delete();
            $this->dispatch('order-updated');
            session()->flash('message', "Tarea '{$taskName}' eliminada.");
        }
    }

    public function toggleTaskStatus($taskId)
    {
        $task = \App\Models\RelatedTask::find($taskId);
        if ($task) {
            if ($task->isDone()) {
                $task->update(['status' => 'todo', 'completed_at' => null]);
            } else {
                $task->update(['status' => 'done', 'completed_at' => now()]);
            }
            $this->dispatch('order-updated');
        }
    }

    public function render()
    {
        $order = $this->orderId 
            ? Order::with(['designer', 'relatedTasks.assignee', 'events', 'dueDateHistories'])->find($this->orderId)
            : null;

        return view('livewire.orders.order-detail-modal', [
            'order' => $order,
            'designers' => Designer::where('active', true)->get(),
            'coreStatuses' => CoreStatus::cases(),
            'substatuses' => Substatus::cases(),
        ]);
    }
}
