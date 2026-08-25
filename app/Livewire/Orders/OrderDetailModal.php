<?php

namespace App\Livewire\Orders;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use App\Services\ClientMatchingService;
use App\Services\TrelloSyncService;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderDetailModal extends Component
{
    public $orderId = null;

    public $showModal = false;

    public $showApprovalModal = false;

    public $showDelayModal = false;

    public $showUnblockModal = false;

    public $unblockReason = '';

    // Edit Mode state
    public $isEditing = false;

    public $editWoNumber = '';

    public $editTrelloCardId = '';

    public $editCompanyName = '';

    public $editLocationName = '';

    public $editResponsiblePerson = '';

    public $editTaskName = '';

    public $editDesignerId = null;

    public $editDesignerIds = [];

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

    // New task fields
    public $newTaskTitle = '';

    public $newTaskDate = '';

    public $newTaskIsWork = true;

    // Trello comments state
    public $trelloComments = [];

    public $newTrelloComment = '';

    public $isLoadingTrelloComments = false;

    public $trelloCommentError = null;

    public function mount($orderId = null)
    {
        $this->orderId = $orderId;
        $this->clientPromisedDate = now()->addWeekdays(2)->toDateString();
        $this->newTaskDate = now()->toDateString();
    }

    #[On('open-order-detail')]
    public function openModal($orderId = null, $startEdit = false)
    {
        if ($orderId) {
            $this->orderId = $orderId;
        }
        $this->showModal = true;
        $this->newTaskDate = now()->toDateString();
        $this->newTaskIsWork = true;

        if ($startEdit) {
            $this->startEditing();
        } else {
            $this->isEditing = false;
        }

        $this->loadTrelloComments();
    }

    public function loadTrelloComments()
    {
        if (! $this->orderId) {
            $this->trelloComments = [];

            return;
        }

        $order = Order::find($this->orderId);
        if (! $order || ! $order->trello_card_id) {
            $this->trelloComments = [];
            $this->trelloCommentError = null;

            return;
        }

        $this->isLoadingTrelloComments = true;
        $this->trelloCommentError = null;

        $res = app(TrelloSyncService::class)->getCardComments($order->trello_card_id);

        $this->isLoadingTrelloComments = false;
        if ($res['success']) {
            $this->trelloComments = $res['comments'];
        } else {
            $this->trelloCommentError = $res['error'] ?? 'Error al obtener comentarios de Trello.';
        }
    }

    public function createCardOnTrello()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        if ($order->trello_card_id) {
            session()->flash('error', 'La orden ya está vinculada a una tarjeta de Trello.');

            return;
        }

        $res = app(TrelloSyncService::class)->createCardOnTrello($order);

        if ($res['success']) {
            $order->refresh();
            $this->editTrelloCardId = $order->trello_card_id;
            session()->flash('message', 'Tarjeta de Trello creada y vinculada exitosamente.');
            $this->dispatch('order-updated');
            $this->loadTrelloComments();
        } else {
            session()->flash('error', 'Error al crear la tarjeta en Trello: '.($res['error'] ?? 'Error desconocido'));
        }
    }

    public function addTrelloComment()
    {
        if (! $this->orderId || empty(trim($this->newTrelloComment))) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        if (! $order->trello_card_id) {
            session()->flash('error', 'La orden no tiene una tarjeta de Trello vinculada.');

            return;
        }

        $commentText = trim($this->newTrelloComment);
        $res = app(TrelloSyncService::class)->addCardComment($order->trello_card_id, $commentText);

        if ($res['success']) {
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'TRELLO_COMMENT_ADDED',
                'actor' => 'Usuario',
                'previous_value' => null,
                'new_value' => null,
                'metadata' => [
                    'comment' => $commentText,
                    'trello_card_id' => $order->trello_card_id,
                ],
            ]);

            $this->newTrelloComment = '';
            $this->loadTrelloComments();
            session()->flash('message', 'Comentario publicado en Trello correctamente.');
            $this->dispatch('order-updated');
        } else {
            $this->trelloCommentError = $res['error'] ?? 'No se pudo publicar el comentario en Trello.';
            session()->flash('error', 'Error al publicar comentario en Trello: '.($res['error'] ?? 'Desconocido'));
        }
    }

    public function startEditing()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::with('designers')->findOrFail($this->orderId);
        $this->editWoNumber = preg_replace('/^WO\s*/i', '', $order->wo_number ?? '');
        $this->editTrelloCardId = $order->trello_card_id ?? '';
        $this->editCompanyName = $order->company_name ?? '';
        $this->editLocationName = $order->location_name ?? '';
        $this->editResponsiblePerson = $order->responsible_person ?? '';
        $this->editTaskName = $order->task_name ?? '';
        $this->editDesignerId = $order->designer_id;
        $this->editDesignerIds = $order->designers->pluck('id')->toArray();
        if (empty($this->editDesignerIds) && $order->designer_id) {
            $this->editDesignerIds = [$order->designer_id];
        }
        $this->editCoreStatus = $order->core_status ? $order->core_status->value : CoreStatus::ENTRANTE->value;
        $this->editSubstatus = $order->substatus ? $order->substatus->value : '';
        $this->editDueDate = $order->current_due_date ? $order->current_due_date->toDateString() : '';
        $this->editClientRevisionCount = $order->client_revision_count ?? 0;
        $this->editInternalRevisionCount = $order->internal_revision_count ?? 0;

        $this->isEditing = true;
    }

    public function toggleDesigner($id)
    {
        $id = (int) $id;
        if (in_array($id, $this->editDesignerIds)) {
            $this->editDesignerIds = array_values(array_diff($this->editDesignerIds, [$id]));
        } else {
            $this->editDesignerIds[] = $id;
        }
    }

    public function cancelEditing()
    {
        $this->isEditing = false;
    }

    public function updatedEditSubstatus($value)
    {
        if ($value === Substatus::ENVIADO_EN_ALTA->value || $value === 'ENVIADO EN ALTA') {
            $this->editCoreStatus = CoreStatus::EN_PRODUCCION->value;
        }
    }

    public function updatedEditCoreStatus($value)
    {
        if ($value === CoreStatus::EN_PRODUCCION->value || $value === 'EN PRODUCCIÓN') {
            $this->editSubstatus = Substatus::ENVIADO_EN_ALTA->value;
        }
    }

    public function acceptPendingWo()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        if (! $order->pending_wo_number) {
            return;
        }

        $oldWo = $order->wo_number ?: 'Sin WO / WO 0000';
        $newWo = $order->pending_wo_number;

        $order->update([
            'wo_number' => $newWo,
            'pending_wo_number' => null,
        ]);

        if ($this->isEditing) {
            $this->editWoNumber = preg_replace('/^WO\s*/i', '', $newWo);
        }

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'WO_UPDATED_FROM_TRELLO',
            'actor' => 'Usuario',
            'previous_value' => $oldWo,
            'new_value' => $newWo,
            'metadata' => [
                'source' => 'Aceptado por usuario en Detalle de Tarjeta',
            ],
        ]);

        session()->flash('message', "Número de WO actualizado a {$newWo} (Trello) correctamente.");
        $this->dispatch('order-updated');
    }

    public function dismissPendingWo()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        $order->update([
            'pending_wo_number' => null,
        ]);

        $currentWoLabel = $order->wo_number ?: 'actual';
        session()->flash('message', "Sugerencia de WO descartada. Se conserva el WO {$currentWoLabel} (DOES).");
        $this->dispatch('order-updated');
    }

    public function openUnblockModal()
    {
        $this->unblockReason = '';
        $this->showUnblockModal = true;
    }

    public function closeUnblockModal()
    {
        $this->showUnblockModal = false;
        $this->unblockReason = '';
    }

    public function selectPresetReason($preset)
    {
        $this->unblockReason = $preset;
    }

    public function confirmUnblock()
    {
        if (! $this->orderId) {
            return;
        }

        $this->validate([
            'unblockReason' => 'required|string|min:3',
        ], [
            'unblockReason.required' => __('Ingresa el motivo o forma en que se resolvió el bloqueo.'),
            'unblockReason.min' => __('El motivo debe contener al menos 3 caracteres.'),
        ]);

        $order = Order::findOrFail($this->orderId);
        $order->unblock($this->unblockReason);

        session()->flash('message', __('Orden :company desbloqueada y devuelta a la lista del diseñador.', ['company' => $order->company_name]));

        $this->closeUnblockModal();
        $this->dispatch('order-updated');
    }

    public function saveOrder($addToWorkspace = false)
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        $cleanWo = trim(preg_replace('/^WO\s*/i', '', $this->editWoNumber ?? ''));

        $cleanTrelloId = trim($this->editTrelloCardId ?? '');
        if (preg_match('/trello\.com\/c\/([^\/]+)/i', $cleanTrelloId, $matches)) {
            $cleanTrelloId = $matches[1];
        }

        if ($this->editSubstatus === Substatus::ENVIADO_EN_ALTA->value || $this->editSubstatus === 'ENVIADO EN ALTA') {
            $this->editCoreStatus = CoreStatus::EN_PRODUCCION->value;
        } elseif ($this->editCoreStatus === CoreStatus::EN_PRODUCCION->value || $this->editCoreStatus === 'EN PRODUCCIÓN') {
            if (empty($this->editSubstatus)) {
                $this->editSubstatus = Substatus::ENVIADO_EN_ALTA->value;
            }
        }

        $previousStatus = $order->core_status;
        $newCoreStatus = ! empty($this->editCoreStatus) ? CoreStatus::tryFrom($this->editCoreStatus) : $order->core_status;

        $cleanLocationName = ! empty($this->editLocationName) ? mb_strtoupper(trim($this->editLocationName), 'UTF-8') : null;

        $newDueDate = ! empty($this->editDueDate) ? $this->editDueDate : null;
        $newSubstatus = ! empty($this->editSubstatus) ? $this->editSubstatus : null;

        if (empty($newDueDate)) {
            if ($newSubstatus === Substatus::OVERDUE->value || $newSubstatus === Substatus::ALMOST_OVERDUE->value) {
                $newSubstatus = null;
                $this->editSubstatus = '';
            }
            app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
        }

        $updateData = [
            'wo_number' => ! empty($cleanWo) ? "WO {$cleanWo}" : null,
            'pending_wo_number' => null,
            'trello_card_id' => ! empty($cleanTrelloId) ? $cleanTrelloId : null,
            'company_name' => $this->editCompanyName,
            'location_name' => $cleanLocationName,
            'responsible_person' => ! empty($this->editResponsiblePerson) ? $this->editResponsiblePerson : null,
            'task_name' => $this->editTaskName,
            'designer_id' => ! empty($this->editDesignerIds) ? reset($this->editDesignerIds) : null,
            'core_status' => $newCoreStatus ?: $order->core_status,
            'substatus' => $newSubstatus,
            'current_due_date' => $newDueDate,
            'client_revision_count' => (int) $this->editClientRevisionCount,
            'internal_revision_count' => (int) $this->editInternalRevisionCount,
            'done_today' => false,
        ];

        if (! empty($this->editCompanyName)) {
            $rawMatchString = $this->editCompanyName.($cleanLocationName ? ' REF '.$cleanLocationName : '');
            $matched = app(ClientMatchingService::class)->matchOrCreate($rawMatchString, $this->editResponsiblePerson);
            if ($matched['client']) {
                $updateData['client_id'] = $matched['client']->id;
                $updateData['company_name'] = $matched['client']->name;
            }
            if ($matched['location']) {
                $updateData['client_location_id'] = $matched['location']->id;
            }
        }

        if ($addToWorkspace) {
            $updateData['in_workspace'] = true;
        }

        $order->update($updateData);
        $order->syncDesigners($this->editDesignerIds);

        if ($newCoreStatus && $previousStatus !== $newCoreStatus) {
            app(AutomationEngine::class)->handleStatusChanged($order->fresh(), $previousStatus, $newCoreStatus);
        }

        // Trigger overdue task creation immediately if updated date is today or overdue
        app(AutomationEngine::class)->checkAndCreateOverdueTask($order->fresh());

        // Safely sync updated title to Trello if card is linked
        if ($order->trello_card_id) {
            app(TrelloSyncService::class)->updateCardOnTrello($order);
        }

        $this->isEditing = false;
        $this->dispatch('order-updated');

        if ($addToWorkspace) {
            session()->flash('message', "Orden {$order->company_name} actualizada y añadida al Workspace activo.");
        } else {
            session()->flash('message', "Orden {$order->company_name} actualizada correctamente.");
        }
    }

    public function duplicateCurrentOrder()
    {
        if (! $this->orderId) {
            return;
        }

        $original = Order::findOrFail($this->orderId);

        $newOrder = Order::create([
            'wo_number' => $original->wo_number ? "{$original->wo_number} (Copia)" : null,
            'company_name' => $original->company_name,
            'task_name' => "{$original->task_name} (Copia)",
            'responsible_person' => $original->responsible_person,
            'designer_id' => $original->designer_id,
            'core_status' => $original->core_status,
            'substatus' => $original->substatus,
            'current_due_date' => $original->current_due_date,
            'in_workspace' => true,
        ]);

        OrderEvent::create([
            'order_id' => $newOrder->id,
            'event_type' => 'ORDER_DUPLICATED',
            'actor' => 'User',
            'previous_value' => null,
            'new_value' => $newOrder->core_status?->value,
            'metadata' => [
                'duplicated_from_id' => $original->id,
                'comment' => "Duplicada a partir de la orden #{$original->id} ({$original->company_name})",
            ],
        ]);

        $this->orderId = $newOrder->id;
        $this->dispatch('order-updated');
        session()->flash('message', "Orden '{$newOrder->company_name}' duplicada correctamente.");
    }

    public function toggleDoneToday()
    {
        if (! $this->orderId) {
            return;
        }
        $order = Order::findOrFail($this->orderId);
        $newDoneToday = ! $order->done_today;
        $order->update(['done_today' => $newDoneToday]);
        if ($newDoneToday) {
            app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
        }
        $this->dispatch('order-updated');
    }

    public function clearDueDate()
    {
        if (! $this->orderId) {
            return;
        }
        $order = Order::findOrFail($this->orderId);
        $updateData = ['current_due_date' => null];
        if ($order->substatus === Substatus::OVERDUE || $order->substatus === Substatus::ALMOST_OVERDUE) {
            $updateData['substatus'] = null;
            $this->editSubstatus = '';
        }
        $order->update($updateData);
        $this->editDueDate = '';
        app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
        $this->dispatch('order-updated');
        session()->flash('message', "Fecha límite de {$order->company_name} establecida a Ninguna (Sin Fecha).");
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
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $order->update(['in_workspace' => false]);
        $this->closeModal();
        $this->dispatch('order-updated');
        session()->flash('message', "Orden {$order->company_name} movida de regreso al Backlog.");
    }

    public function deleteOrder()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $name = $order->company_name ?? 'Orden';

        $order->delete();

        $this->closeModal();
        $this->dispatch('order-updated');
        session()->flash('message', "Orden '{$name}' movida a la Papelera de Reciclaje.");
    }

    public function addToWorkspaceDirectly()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $order->update(['in_workspace' => true]);
        $this->dispatch('order-updated');
        session()->flash('message', "Orden {$order->company_name} añadida al Workspace activo.");
    }

    public function submitApproval()
    {
        if (! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);

        app(AutomationEngine::class)->processApproval(
            $order,
            (bool) $this->measuresConfirmed,
            (bool) $this->estimateApproved
        );

        $this->closeModal();
        $this->dispatch('order-updated');
        session()->flash('message', "Aprobación procesada para {$order->company_name}.");
    }

    public function submitDelayResolution()
    {
        if (! $this->orderId) {
            return;
        }

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
        if (empty(trim($this->newTaskTitle)) || ! $this->orderId) {
            return;
        }

        $order = Order::findOrFail($this->orderId);
        $taskDate = ! empty($this->newTaskDate) ? Carbon::parse($this->newTaskDate) : now();
        $isWork = (bool) $this->newTaskIsWork;

        $subtask = $order->relatedTasks()->create([
            'title' => trim($this->newTaskTitle),
            'type' => RelatedTaskType::RESOLVER,
            'status' => 'todo',
            'assignee_id' => $order->getPrimaryDesignerId(),
            'scheduled_date' => $taskDate->toDateString(),
            'due_date' => $taskDate->toDateString(),
            'priority' => 'normal',
            'is_work_task' => $isWork,
        ]);

        if ($isWork && $taskDate->isToday()) {
            if ($order->core_status !== CoreStatus::ON_HOLD && $order->core_status !== CoreStatus::EN_PRODUCCION && $order->core_status !== CoreStatus::ARCHIVED) {
                $previousStatus = $order->core_status;
                $updateData = [
                    'scheduled_date' => $taskDate->toDateString(),
                    'core_status' => CoreStatus::TO_DO_TODAY,
                ];
                if ($previousStatus === CoreStatus::ENVIADO_A_CAMILA) {
                    $updateData['substatus'] = Substatus::CAMBIOS_CAMILA;
                } elseif ($previousStatus === CoreStatus::ENVIADO_AL_CLIENTE) {
                    $updateData['substatus'] = Substatus::CAMBIOS_CLIENTE;
                }
                $order->update($updateData);
            }
        }

        $this->newTaskTitle = '';
        $this->dispatch('order-updated');
    }

    public function deleteTask($taskId)
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $taskName = $task->title;
            $task->delete();
            $this->dispatch('order-updated');
            session()->flash('message', "Tarea '{$taskName}' eliminada.");
        }
    }

    public function dismissTask($taskId)
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $taskName = $task->title;
            $task->delete();
            $this->dispatch('order-updated');
            session()->flash('message', "Subtarea '{$taskName}' descartada.");
        }
    }

    public function toggleTaskStatus($taskId)
    {
        $task = RelatedTask::find($taskId);
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
            'existingCompanies' => Order::inWorkspace()
                ->whereNotNull('company_name')
                ->where('company_name', '!=', '')
                ->distinct()
                ->orderBy('company_name')
                ->pluck('company_name'),
            'existingResponsibles' => Order::inWorkspace()
                ->whereNotNull('responsible_person')
                ->where('responsible_person', '!=', '')
                ->distinct()
                ->orderBy('responsible_person')
                ->pluck('responsible_person'),
            'availableTrelloCards' => Order::whereNotNull('trello_card_id')
                ->where('trello_card_id', '!=', '')
                ->orderBy('trello_created_at', 'desc')
                ->take(100)
                ->get(['id', 'trello_card_id', 'company_name', 'task_name', 'trello_title', 'wo_number']),
        ]);
    }
}
