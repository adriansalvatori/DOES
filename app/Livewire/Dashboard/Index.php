<?php

namespace App\Livewire\Dashboard;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\RelatedTask;
use Livewire\Component;

class Index extends Component
{
    public $selectedDesigner = 'all';
    public $activeTab = 'today'; // today, overdue, camila, client, resolver, alta
    public $search = '';

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function markDoneToday($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update(['done_today' => true]);
        
        app(\App\Services\AutomationEngine::class)->runDailyAutomations();
        
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

    public function render()
    {
        $query = Order::inWorkspace()->with(['designer', 'relatedTasks']);

        if ($this->selectedDesigner !== 'all') {
            $query->where('designer_id', $this->selectedDesigner);
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', "%{$this->search}%")
                  ->orWhere('task_name', 'like', "%{$this->search}%");
            });
        }

        $allOrders = (clone $query)->get();

        $overdueOrders = $allOrders->filter(fn($o) => $o->substatus === Substatus::OVERDUE || ($o->current_due_date && $o->current_due_date->isPast() && !$o->isPaused() && $o->core_status !== CoreStatus::EN_PRODUCCION));
        
        $toDoTodayOrders = $allOrders->filter(fn($o) => $o->core_status === CoreStatus::TO_DO_TODAY || $o->scheduled_date?->isToday());

        $clientFollowUpTasks = RelatedTask::with(['order', 'assignee'])
            ->where('status', 'todo')
            ->where('type', \App\Enums\RelatedTaskType::FOLLOW_UP_CLIENTE)
            ->get();

        $camilaFollowUpTasks = RelatedTask::with(['order', 'assignee'])
            ->where('status', 'todo')
            ->where('type', \App\Enums\RelatedTaskType::FOLLOW_UP_CAMILA)
            ->get();

        $resolverOrders = $allOrders->filter(fn($o) => $o->substatus === Substatus::BLOQUEADA || $o->substatus === Substatus::FALTA_APROBACION_ESTIMADO || $o->customer_service_required);

        $readyForAltaOrders = $allOrders->filter(fn($o) => $o->substatus === Substatus::PONER_EN_ALTA);

        return view('livewire.dashboard.index', [
            'overdueOrders' => $overdueOrders,
            'toDoTodayOrders' => $toDoTodayOrders,
            'clientFollowUpTasks' => $clientFollowUpTasks,
            'camilaFollowUpTasks' => $camilaFollowUpTasks,
            'resolverOrders' => $resolverOrders,
            'readyForAltaOrders' => $readyForAltaOrders,
            'designers' => \App\Models\Designer::where('active', true)->get(),
        ])->layout('components.layouts.app', ['title' => 'Dashboard Operativo - Kudos Design Ops']);
    }
}
