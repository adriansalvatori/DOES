<?php

namespace App\Livewire\Kanban;

use App\Enums\CoreStatus;
use App\Models\Order;
use App\Services\AutomationEngine;
use App\Services\TrelloSyncService;
use Livewire\Attributes\On;
use Livewire\Component;

class Board extends Component
{
    public $search = '';
    public $designerFilter = 'all';
    public $substatusFilter = 'all';
    public $columnGroup = 'all'; // all, incoming, in_progress, final

    public bool $showOnHoldModal = false;
    public ?int $pendingOnHoldOrderId = null;
    public string $onHoldReason = '';

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

        // Update local state instantly
        $order->update(['core_status' => $newStatus]);

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
        if (!$this->pendingOnHoldOrderId) return;

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
        \App\Models\OrderEvent::create([
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
            } catch (\Throwable $e) {}
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

    #[On('order-updated')]
    #[On('task-added')]
    public function refreshBoard()
    {
        // Automatically re-renders board when tasks or orders are created/updated
    }

    public function toggleTaskComplete($taskId)
    {
        $task = \App\Models\RelatedTask::findOrFail($taskId);
        if ($task->isDone()) {
            $task->update(['status' => 'todo', 'completed_at' => null]);
        } else {
            $task->update(['status' => 'done', 'completed_at' => now()]);
        }

        session()->flash('message', "Tarea '{$task->title}' actualizada.");
    }

    public function render()
    {
        $query = Order::inWorkspace()->with(['designer', 'relatedTasks']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', "%{$this->search}%")
                  ->orWhere('task_name', 'like', "%{$this->search}%")
                  ->orWhere('wo_number', 'like', "%{$this->search}%")
                  ->orWhere('responsible_person', 'like', "%{$this->search}%");
            });
        }

        if ($this->designerFilter !== 'all') {
            $query->where('designer_id', $this->designerFilter);
        }

        if ($this->substatusFilter !== 'all') {
            $query->where('substatus', $this->substatusFilter);
        }

        $orders = $query->get();

        foreach ($orders as $order) {
            if ($order->isOverdue()) {
                app(AutomationEngine::class)->checkAndCreateOverdueTask($order);
            }
        }

        // Load all related tasks belonging to workspace orders
        $tasksQuery = \App\Models\RelatedTask::whereHas('order', fn($q) => $q->inWorkspace())->with(['order', 'assignee']);
        if (!empty($this->search)) {
            $tasksQuery->where('title', 'like', "%{$this->search}%");
        }
        $relatedTasks = $tasksQuery->get();

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
        ];

        $columns = match($this->columnGroup) {
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
            ],
            default => $allColumns,
        };

        return view('livewire.kanban.board', [
            'columns' => $columns,
            'allColumns' => $allColumns,
            'orders' => $orders,
            'relatedTasks' => $relatedTasks,
            'designers' => \App\Models\Designer::where('active', true)->get(),
        ])->layout('components.layouts.app', ['title' => 'Kanban Board - Kudos Design Ops']);
    }
}
