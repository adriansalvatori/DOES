<?php

namespace App\Livewire\Dashboard;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public $selectedDesigner = 'all';

    public $activeTab = 'all'; // 'all' (default overview), today, overdue, camila, client, resolver, alta, pronostico, new_orders

    public $userRole = 'all'; // 'all', 'designer', 'manager'

    public $search = '';

    #[On('order-updated')]
    public function refreshDashboard(): void
    {
        // Re-renders the dashboard view automatically when orders or tasks are updated in card flyout
    }

    public function setUserRole($role)
    {
        $this->userRole = $role;
        if ($role === 'designer') {
            $this->activeTab = 'today';
        } elseif ($role === 'manager') {
            $this->activeTab = 'client';
        } else {
            $this->activeTab = 'all';
        }
    }

    public function setActiveTab($tab)
    {
        if ($this->activeTab === $tab) {
            $this->activeTab = 'all';
        } else {
            $this->activeTab = $tab;
        }
    }

    public function markDoneToday($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update(['done_today' => true]);

        app(AutomationEngine::class)->runDailyAutomations();

        session()->flash('message', "Orden {$order->company_name} marcada como realizada hoy.");
    }

    public function completeTask($taskId)
    {
        $task = RelatedTask::findOrFail($taskId);
        $task->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);

        session()->flash('message', "Tarea '{$task->title}' completada.");
    }

    public function moveToWorkspace($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'in_workspace' => true,
            'is_new_from_trello' => false,
        ]);

        session()->flash('message', "Orden {$order->company_name} movida al Workspace activo.");
    }

    public function render()
    {
        $query = Order::inWorkspace()->prioritizeUrgente()->with(['designer', 'designers', 'relatedTasks']);

        if ($this->selectedDesigner !== 'all') {
            $query->where('designer_id', $this->selectedDesigner);
        }

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', "%{$this->search}%")
                    ->orWhere('task_name', 'like', "%{$this->search}%");
            });
        }

        $allOrders = (clone $query)->get();

        $overdueOrders = $allOrders->filter(fn ($o) => $o->isOverdue());

        $toDoTodayOrders = $allOrders->filter(fn ($o) => $o->core_status === CoreStatus::TO_DO_TODAY || $o->scheduled_date?->isToday());

        $toDoTodayTasks = RelatedTask::with(['order', 'assignee'])
            ->whereHas('order', fn ($q) => $q->inWorkspace())
            ->where('status', 'todo')
            ->where(function ($q) {
                $q->whereDate('scheduled_date', today())
                    ->orWhereDate('due_date', today());
            })
            ->get();

        $clientFollowUpTasks = RelatedTask::with(['order', 'assignee'])
            ->whereHas('order', fn ($q) => $q->inWorkspace())
            ->where('status', 'todo')
            ->where('type', RelatedTaskType::FOLLOW_UP_CLIENTE)
            ->get();

        $camilaFollowUpTasks = RelatedTask::with(['order', 'assignee'])
            ->whereHas('order', fn ($q) => $q->inWorkspace())
            ->where('status', 'todo')
            ->where('type', RelatedTaskType::FOLLOW_UP_CAMILA)
            ->get();

        $resolverOrders = $allOrders->filter(fn ($o) => $o->substatus === Substatus::BLOQUEADA || $o->substatus === Substatus::FALTA_APROBACION_ESTIMADO || $o->customer_service_required);

        $readyForAltaOrders = $allOrders->filter(fn ($o) => $o->substatus === Substatus::PONER_EN_ALTA);

        $startOfWeek = now()->startOfWeek();
        $pronosticoAltaOrders = $allOrders->filter(function ($o) use ($startOfWeek) {
            $isEnviado = $o->core_status === CoreStatus::ENVIADO_AL_CLIENTE || $o->core_status?->value === 'ENVIADO AL CLIENTE';
            $isThisWeek = ($o->updated_at && $o->updated_at->gte($startOfWeek)) || ($o->created_at && $o->created_at->gte($startOfWeek));

            return $isEnviado && $isThisWeek;
        });

        $newTrelloOrders = Order::inBacklog()->newFromTrello()->prioritizeUrgente()->with(['designer', 'designers'])->get();

        return view('livewire.dashboard.index', [
            'overdueOrders' => $overdueOrders,
            'toDoTodayOrders' => $toDoTodayOrders,
            'toDoTodayTasks' => $toDoTodayTasks,
            'clientFollowUpTasks' => $clientFollowUpTasks,
            'camilaFollowUpTasks' => $camilaFollowUpTasks,
            'resolverOrders' => $resolverOrders,
            'readyForAltaOrders' => $readyForAltaOrders,
            'pronosticoAltaOrders' => $pronosticoAltaOrders,
            'newTrelloOrders' => $newTrelloOrders,
            'designers' => Designer::where('active', true)->get(),
        ])->layout('components.layouts.app', ['title' => 'Dashboard Operativo - Kudos Design Ops']);
    }
}
