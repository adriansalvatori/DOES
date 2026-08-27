<?php

namespace App\Livewire\Kanban;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use App\Services\SlaEngine;
use App\Services\TrelloSyncService;
use Livewire\Attributes\On;
use Livewire\Component;

class Board extends Component
{
    public $search = '';

    public $designerFilter = 'all';

    public $substatusFilter = 'all';

    public $companyFilter = 'all';

    public $responsibleFilter = 'all';

    public $columnGroup = 'all'; // all, incoming, in_progress, final

    public bool $showOnHoldModal = false;

    public ?int $pendingOnHoldOrderId = null;

    public string $onHoldReason = '';

    public bool $showStandaloneTaskCards = false;

    public function toggleStandaloneTaskCards(): void
    {
        $this->showStandaloneTaskCards = ! $this->showStandaloneTaskCards;
    }

    public function getSearchResultsProperty()
    {
        if (strlen(trim($this->search)) < 2) {
            return collect();
        }

        return Order::inWorkspace()
            ->with('designer')
            ->where(function ($q) {
                $q->where('company_name', 'like', "%{$this->search}%")
                    ->orWhere('task_name', 'like', "%{$this->search}%")
                    ->orWhere('wo_number', 'like', "%{$this->search}%")
                    ->orWhere('responsible_person', 'like', "%{$this->search}%");
            })
            ->take(8)
            ->get();
    }

    public function selectSearchResult($orderId)
    {
        $this->dispatch('open-order-detail', orderId: $orderId);
    }

    public function moveOrder($orderId, $newStatusValue)
    {
        $order = Order::findOrFail($orderId);
        $previousStatus = $order->core_status;
        $newStatus = CoreStatus::from($newStatusValue);

        if ($newStatus === CoreStatus::ON_HOLD) {
            $this->pendingOnHoldOrderId = $orderId;
            $this->onHoldReason = '';
            $this->showOnHoldModal = true;

            return;
        }

        if ($newStatus === CoreStatus::ENTRANTE) {
            $designerStatus = match ($order->designer?->name) {
                'Adrián' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
                'César' => CoreStatus::CESAR_ORDERS_RECEIVED,
                default => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            };

            $order->update([
                'core_status' => $designerStatus,
                'substatus' => Substatus::BLOQUEADA,
            ]);

            RelatedTask::create([
                'order_id' => $order->id,
                'title' => "Bloqueado: {$order->company_name} - {$order->task_name}",
                'type' => RelatedTaskType::BLOCKED,
                'status' => 'todo',
                'assignee_id' => $order->designer_id,
                'due_date' => now()->toDateString(),
                'priority' => 'high',
            ]);

            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'ORDER_BLOCKED',
                'actor' => 'User',
                'previous_value' => $previousStatus->value,
                'new_value' => $designerStatus->value,
                'metadata' => [
                    'substatus' => Substatus::BLOQUEADA->value,
                    'comment' => 'Orden asignada a la lista del diseñador con etiqueta BLOQUEADA y subtarea creada en BLOCKED.',
                ],
            ]);

            $this->dispatch('order-updated');
            session()->flash('message', "Orden {$order->company_name} marcada como Bloqueada en la lista del diseñador y subtarea agregada a BLOCKED.");

            return;
        }

        // Update local state instantly
        if ($newStatus === CoreStatus::EN_PRODUCCION) {
            $order->update([
                'core_status' => $newStatus,
                'substatus' => Substatus::ENVIADO_EN_ALTA,
            ]);
        } else {
            $order->update(['core_status' => $newStatus]);
        }

        // Run local workflow automations
        app(AutomationEngine::class)->handleStatusChanged($order, $previousStatus, $newStatus);

        // Optionally attempt Trello sync in background without interrupting UI
        if ($order->trello_card_id) {
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($order);
            } catch (\Throwable $e) {
                // Ignore remote network error so local drag-and-drop state is preserved
            }
        }

        session()->flash('message', "Orden {$order->company_name} movida a {$newStatus->label()}.");
    }

    public function confirmOnHold()
    {
        if (! $this->pendingOnHoldOrderId) {
            return;
        }

        $this->validate([
            'onHoldReason' => 'required|string|min:3',
        ], [
            'onHoldReason.required' => 'Debes ingresar un motivo para poner la orden en On Hold.',
            'onHoldReason.min' => 'El motivo debe tener al menos 3 caracteres.',
        ]);

        $order = Order::findOrFail($this->pendingOnHoldOrderId);
        $previousStatus = $order->core_status;
        $newStatus = CoreStatus::ON_HOLD;

        $order->update(['core_status' => $newStatus]);

        // Run local workflow automations
        app(AutomationEngine::class)->handleStatusChanged($order, $previousStatus, $newStatus);

        // Log event in OrderEvent with reason
        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'MOVED_TO_ON_HOLD',
            'actor' => 'User',
            'previous_value' => $previousStatus->value,
            'new_value' => $newStatus->value,
            'metadata' => [
                'reason' => $this->onHoldReason,
                'comment' => $this->onHoldReason,
            ],
        ]);

        if ($order->trello_card_id) {
            try {
                app(TrelloSyncService::class)->updateCardOnTrello($order);
            } catch (\Throwable $e) {
            }
        }

        $this->showOnHoldModal = false;
        $this->pendingOnHoldOrderId = null;
        $this->onHoldReason = '';

        $this->dispatch('order-updated');
        session()->flash('message', "Orden {$order->company_name} movida a On Hold.");
    }

    public function cancelOnHold()
    {
        $this->showOnHoldModal = false;
        $this->pendingOnHoldOrderId = null;
        $this->onHoldReason = '';
    }

    public function duplicateOrder($orderId)
    {
        // Dispatch to CreateOrderModal's open-duplicate-order so user can edit before saving
        $this->dispatch('open-duplicate-order', orderId: $orderId);
    }

    public function trashOrder($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->delete(); // soft delete

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_TRASHED',
            'actor' => 'User',
            'previous_value' => $order->core_status?->value,
            'new_value' => 'TRASHED',
            'metadata' => ['comment' => 'Orden movida a la papelera.'],
        ]);

        $this->dispatch('order-updated');
        session()->flash('message', "Orden '{$order->company_name}' enviada a la papelera.");
    }

    #[On('order-updated')]
    #[On('task-added')]
    public function refreshBoard()
    {
        // Automatically re-renders board when tasks or orders are created/updated
    }

    public function toggleTaskComplete($taskId)
    {
        $task = RelatedTask::findOrFail($taskId);
        if ($task->isDone()) {
            $task->update(['status' => 'todo', 'completed_at' => null]);
        } else {
            $task->update(['status' => 'done', 'completed_at' => now()]);
        }

        session()->flash('message', "Tarea '{$task->title}' actualizada.");
    }

    public function deleteTask($taskId)
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $taskTitle = $task->title;
            $task->delete();
            $this->dispatch('order-updated');
            session()->flash('message', "Tarea '{$taskTitle}' eliminada.");
        }
    }

    public function toggleDoneToday($orderId)
    {
        $order = Order::findOrFail($orderId);
        $newDoneToday = ! $order->done_today;
        $order->update(['done_today' => $newDoneToday]);
        if ($newDoneToday) {
            app(AutomationEngine::class)->dismissPendingOverdueTasks($order);
        }
        $this->dispatch('order-updated');
    }

    public function render()
    {
        $query = Order::inWorkspace()->prioritizeUrgente()->with(['designer', 'designers', 'relatedTasks.assignee']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', "%{$this->search}%")
                    ->orWhere('task_name', 'like', "%{$this->search}%")
                    ->orWhere('wo_number', 'like', "%{$this->search}%")
                    ->orWhere('responsible_person', 'like', "%{$this->search}%");
            });
        }

        if ($this->designerFilter !== 'all') {
            $query->where(function ($q) {
                $q->where('designer_id', $this->designerFilter)
                    ->orWhereHas('designers', fn ($dq) => $dq->where('designers.id', $this->designerFilter));
            });
        }

        if ($this->substatusFilter !== 'all') {
            $query->where('substatus', $this->substatusFilter);
        }

        if ($this->companyFilter !== 'all') {
            $query->where('company_name', $this->companyFilter);
        }

        if ($this->responsibleFilter !== 'all') {
            $query->where('responsible_person', $this->responsibleFilter);
        }

        $orders = $query->get();

        foreach ($orders as $order) {
            app(SlaEngine::class)->checkOverdue($order);
        }

        if ($this->showStandaloneTaskCards) {
            $tasksQuery = RelatedTask::whereHas('order', function ($q) {
                $q->inWorkspace()->whereNotIn('core_status', [CoreStatus::ARCHIVED->value, CoreStatus::ON_HOLD->value]);
                if ($this->designerFilter !== 'all') {
                    $q->where('designer_id', $this->designerFilter);
                }
            })->with(['order', 'assignee']);
            if (! empty($this->search)) {
                $tasksQuery->where('title', 'like', "%{$this->search}%");
            }
            $relatedTasks = $tasksQuery->get();
        } else {
            $relatedTasks = collect();
        }

        $allColumns = [
            CoreStatus::ENTRANTE,
            CoreStatus::EURALIZ_ORDERS_RECEIVED,
            CoreStatus::ADRIAN_ORDERS_RECEIVED,
            CoreStatus::CESAR_ORDERS_RECEIVED,
            CoreStatus::TO_DO_TODAY,
            CoreStatus::ENVIADO_A_CAMILA,
            CoreStatus::ENVIADO_AL_CLIENTE,
            CoreStatus::ON_HOLD,
            CoreStatus::EN_PRODUCCION,
            CoreStatus::ARCHIVED,
        ];

        $columns = match ($this->columnGroup) {
            'incoming' => [
                CoreStatus::ENTRANTE,
                CoreStatus::EURALIZ_ORDERS_RECEIVED,
                CoreStatus::ADRIAN_ORDERS_RECEIVED,
                CoreStatus::CESAR_ORDERS_RECEIVED,
            ],
            'in_progress' => [
                CoreStatus::TO_DO_TODAY,
                CoreStatus::ENVIADO_A_CAMILA,
                CoreStatus::ENVIADO_AL_CLIENTE,
            ],
            'final' => [
                CoreStatus::ON_HOLD,
                CoreStatus::EN_PRODUCCION,
                CoreStatus::ARCHIVED,
            ],
            default => $allColumns,
        };

        return view('livewire.kanban.board', [
            'columns' => $columns,
            'allColumns' => $allColumns,
            'orders' => $orders,
            'relatedTasks' => $relatedTasks,
            'newOrdersCount' => Order::inBacklog()->newFromTrello()->count(),
            'designers' => Designer::where('active', true)->get(),
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
        ])->layout('components.layouts.app', ['title' => 'Kanban Board - Kudos Design Ops']);
    }
}
