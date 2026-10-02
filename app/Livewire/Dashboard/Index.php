<?php

namespace App\Livewire\Dashboard;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public $selectedDesigner = 'all';

    public $activeTab = 'all'; // 'all' (default overview), today, overdue, camila, client, resolver, alta, pronostico, new_orders

    public $userRole = 'all'; // 'all', 'designer', 'manager'

    public bool $showResolveModal = false;

    public ?int $resolveOrderId = null;

    public ?Order $resolveOrder = null;

    public string $resolveComment = '';

    public bool $showCamilaModal = false;

    public ?int $camilaTaskId = null;

    public ?RelatedTask $camilaTask = null;

    public ?Order $camilaOrder = null;

    public string $camilaComment = '';

    public function mount(): void
    {
        $user = Auth::user();
        if ($user && $user->isDesigner() && $user->designer) {
            $this->selectedDesigner = (string) $user->designer->id;
            $this->userRole = 'designer';
            $this->activeTab = 'today';
        }
    }

    #[On('order-updated')]
    #[On('order-created')]
    #[On('order-deleted')]
    #[On('task-added')]
    public function refreshDashboard(): void
    {
        // Re-renders the dashboard view automatically when orders or tasks are updated
    }

    public function selectDesigner(string $designerId): void
    {
        $this->selectedDesigner = $designerId;
    }

    public function setUserRole(string $role): void
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

    public function setActiveTab(string $tab): void
    {
        $user = Auth::user();
        if ($user && $user->isDesigner() && $tab === 'resolver') {
            return;
        }

        if ($this->activeTab === $tab) {
            $this->activeTab = 'all';
        } else {
            $this->activeTab = $tab;
        }
    }

    public function markDoneToday(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $newDoneToday = ! $order->done_today;
        $order->update(['done_today' => $newDoneToday]);

        if ($newDoneToday && $order->core_status === CoreStatus::TO_DO_TODAY && $order->substatus === Substatus::PONER_EN_ALTA) {
            $order->update([
                'core_status' => CoreStatus::EN_PRODUCCION,
                'substatus' => null,
                'done_today' => false,
            ]);
        }

        $hasCompletedProofTask = $order->relatedTasks()
            ->where('title', 'like', '%enviar proof al cliente%')
            ->where('status', 'done')
            ->exists();

        if ($newDoneToday && $order->core_status === CoreStatus::TO_DO_TODAY && $hasCompletedProofTask) {
            $prev = $order->core_status;
            $order->update([
                'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
                'done_today' => true,
                'last_sent_to_client_at' => now(),
            ]);
            app(AutomationEngine::class)->handleStatusChanged($order, $prev, CoreStatus::ENVIADO_AL_CLIENTE);
        }

        $msg = $newDoneToday
            ? __('Orden :company marcada como realizada hoy.', ['company' => $order->company_name])
            : __('Orden :company desmarcada de realizada hoy.', ['company' => $order->company_name]);

        session()->flash('message', $msg);
    }

    public function completeTask(int $taskId): void
    {
        $task = RelatedTask::findOrFail($taskId);
        $task->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);

        if (str_contains(mb_strtolower($task->title), 'enviar proof al cliente') && $task->order) {
            $order = $task->order;
            if ($order->core_status === CoreStatus::TO_DO_TODAY && $order->done_today) {
                $prev = $order->core_status;
                $order->update([
                    'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
                    'done_today' => true,
                    'last_sent_to_client_at' => now(),
                ]);
                app(AutomationEngine::class)->handleStatusChanged($order, $prev, CoreStatus::ENVIADO_AL_CLIENTE);
            }
        }

        session()->flash('message', __('Tarea \':title\' completada.', ['title' => $task->title]));
    }

    public function openCamilaModal(int $taskId): void
    {
        $this->camilaTaskId = $taskId;
        $this->camilaTask = RelatedTask::with(['order.designer', 'order.designers'])->find($taskId);
        $this->camilaOrder = $this->camilaTask?->order;
        $this->camilaComment = '';
        $this->showCamilaModal = true;
    }

    public function closeCamilaModal(): void
    {
        $this->showCamilaModal = false;
        $this->camilaTaskId = null;
        $this->camilaTask = null;
        $this->camilaOrder = null;
        $this->camilaComment = '';
    }

    public function camilaRequestChanges(): void
    {
        if (! $this->camilaTaskId) {
            return;
        }

        $task = RelatedTask::with(['order.designer', 'order.designers'])->find($this->camilaTaskId);
        if (! $task || ! $task->order) {
            return;
        }

        $order = $task->order;

        $task->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);

        $prevStatus = $order->core_status;
        $comment = ! empty(trim($this->camilaComment)) ? trim($this->camilaComment) : __('Ajustes solicitados por Camila');

        $order->update([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::CAMBIOS_CAMILA,
            'internal_revision_count' => ($order->internal_revision_count ?? 0) + 1,
            'done_today' => false,
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'CAMILA_CHANGES_REQUESTED',
            'actor' => Auth::user()?->name ?? 'Camila',
            'previous_value' => $prevStatus?->value,
            'new_value' => CoreStatus::TO_DO_TODAY->value,
            'metadata' => [
                'substatus' => Substatus::CAMBIOS_CAMILA->value,
                'comment' => $comment,
            ],
        ]);

        RelatedTask::create([
            'order_id' => $order->id,
            'title' => mb_substr(__('Ajustes Camila: :comment', ['comment' => $comment]), 0, 190),
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'assignee_id' => $order->getPrimaryDesignerId(),
            'scheduled_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'priority' => 'high',
            'is_work_task' => true,
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, $prevStatus, CoreStatus::TO_DO_TODAY);
        $order->update(['substatus' => Substatus::CAMBIOS_CAMILA]);

        session()->flash('message', __('Ajustes de Camila registrados. Orden :company movida a Para Hoy.', ['company' => $order->company_name]));
        $this->closeCamilaModal();
        $this->dispatch('order-updated');
    }

    public function camilaPreApprove(): void
    {
        if (! $this->camilaTaskId) {
            return;
        }

        $task = RelatedTask::with(['order.designer', 'order.designers'])->find($this->camilaTaskId);
        if (! $task || ! $task->order) {
            return;
        }

        $order = $task->order;

        $task->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);

        $prevStatus = $order->core_status;
        $comment = ! empty(trim($this->camilaComment)) ? trim($this->camilaComment) : __('Camila pre-aprueba el diseño. Listo para enviar al cliente.');

        $order->update([
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => null,
            'done_today' => false,
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'CAMILA_PRE_APPROVED',
            'actor' => Auth::user()?->name ?? 'Camila',
            'previous_value' => $prevStatus?->value,
            'new_value' => CoreStatus::TO_DO_TODAY->value,
            'metadata' => [
                'comment' => $comment,
                'action' => 'PRE_APPROVE_SEND_TO_CLIENT',
            ],
        ]);

        RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'enviar proof al cliente (camila pre-approves)',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'assignee_id' => $order->getPrimaryDesignerId(),
            'scheduled_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'priority' => 'high',
            'is_work_task' => true,
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, $prevStatus, CoreStatus::TO_DO_TODAY);
        $order->update(['substatus' => null]);

        session()->flash('message', __('Pre-aprobado por Camila. Subtarea creada para enviar proof al cliente.'));
        $this->closeCamilaModal();
        $this->dispatch('order-updated');
    }

    public function camilaAddComment(): void
    {
        if (! $this->camilaTaskId) {
            return;
        }

        $task = RelatedTask::with(['order'])->find($this->camilaTaskId);
        if (! $task || ! $task->order) {
            return;
        }

        $order = $task->order;

        $this->validate([
            'camilaComment' => 'required|string|min:3',
        ], [
            'camilaComment.required' => __('Ingresa el comentario o novedad para la orden.'),
            'camilaComment.min' => __('El comentario debe tener al menos 3 caracteres.'),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'CAMILA_NOTE',
            'actor' => Auth::user()?->name ?? 'Camila',
            'previous_value' => $order->core_status?->value,
            'new_value' => $order->core_status?->value,
            'metadata' => [
                'comment' => $this->camilaComment,
                'context' => 'Revisión Camila',
            ],
        ]);

        $order->touch();

        session()->flash('message', __('Comentario registrado en el timeline de la orden :company.', ['company' => $order->company_name]));
        $this->closeCamilaModal();
        $this->dispatch('order-updated');
    }

    public function moveToWorkspace(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'in_workspace' => true,
            'is_new_from_trello' => false,
        ]);

        session()->flash('message', __('Orden :company movida al Workspace activo.', ['company' => $order->company_name]));
    }

    public function openResolveModal(int $orderId): void
    {
        $this->resolveOrderId = $orderId;
        $this->resolveOrder = Order::with(['designer', 'designers', 'clientLocation'])->find($orderId);
        $this->resolveComment = '';
        $this->showResolveModal = true;
    }

    public function closeResolveModal(): void
    {
        $this->showResolveModal = false;
        $this->resolveOrderId = null;
        $this->resolveOrder = null;
        $this->resolveComment = '';
    }

    public function unblockOrder(): void
    {
        if (! $this->resolveOrderId) {
            return;
        }

        $order = Order::findOrFail($this->resolveOrderId);
        $reason = ! empty(trim($this->resolveComment)) ? trim($this->resolveComment) : __('Desbloqueado desde Centro de Control');
        $order->unblock($reason, Auth::user()?->name);

        session()->flash('message', __('Orden :company desbloqueada y devuelta a la cola activa de trabajo.', ['company' => $order->company_name]));
        $this->closeResolveModal();
        $this->dispatch('order-updated');
    }

    public function keepOrderBlocked(): void
    {
        if (! $this->resolveOrderId) {
            return;
        }

        $this->validate([
            'resolveComment' => 'required|string|min:3',
        ], [
            'resolveComment.required' => __('Ingresa una nota o comentario sobre el seguimiento del bloqueo.'),
            'resolveComment.min' => __('La nota debe contener al menos 3 caracteres.'),
        ]);

        $order = Order::findOrFail($this->resolveOrderId);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_BLOCK_REVIEWED',
            'actor' => Auth::user()?->name ?? 'Usuario',
            'previous_value' => $order->substatus?->value,
            'new_value' => $order->substatus?->value,
            'metadata' => [
                'comment' => $this->resolveComment,
                'action' => 'KEEP_BLOCKED',
                'reviewed_at' => now()->toIso8601String(),
            ],
        ]);

        $order->touch();

        session()->flash('message', __('Seguimiento registrado para la orden :company. Se mantiene bloqueada.', ['company' => $order->company_name]));
        $this->closeResolveModal();
        $this->dispatch('order-updated');
    }

    public function render()
    {
        $query = Order::inWorkspace()->prioritizeUrgente()->with(['designer', 'designers', 'clientLocation']);

        if ($this->selectedDesigner !== 'all') {
            $query->where(function ($q) {
                $q->where('designer_id', $this->selectedDesigner)
                    ->orWhereHas('designers', fn ($d) => $d->where('designers.id', $this->selectedDesigner));
            });
        }

        $allOrders = (clone $query)->get();

        $overdueOrders = $allOrders->filter(fn ($o) => $o->isOverdue());

        $toDoTodayOrders = $allOrders->filter(fn ($o) => $o->core_status === CoreStatus::TO_DO_TODAY || $o->scheduled_date?->isToday());

        $tasksQuery = RelatedTask::with(['order', 'assignee'])
            ->whereHas('order', fn ($q) => $q->inWorkspace())
            ->where('status', 'todo');

        if ($this->selectedDesigner !== 'all') {
            $tasksQuery->where(function ($tq) {
                $tq->where('assignee_id', $this->selectedDesigner)
                    ->orWhereHas('order', function ($oq) {
                        $oq->where('designer_id', $this->selectedDesigner)
                            ->orWhereHas('designers', fn ($d) => $d->where('designers.id', $this->selectedDesigner));
                    });
            });
        }

        $toDoTodayTasks = (clone $tasksQuery)
            ->where(function ($q) {
                $q->whereDate('scheduled_date', today())
                    ->orWhereDate('due_date', today());
            })
            ->get();

        $clientFollowUpTasks = (clone $tasksQuery)
            ->where('type', RelatedTaskType::FOLLOW_UP_CLIENTE)
            ->get();

        $camilaFollowUpTasks = (clone $tasksQuery)
            ->where('type', RelatedTaskType::FOLLOW_UP_CAMILA)
            ->get();

        $resolverOrders = $allOrders->filter(fn ($o) => $o->isBlocked());

        $readyForAltaOrders = $allOrders->filter(fn ($o) => $o->substatus === Substatus::PONER_EN_ALTA);

        $startOfWeek = now()->startOfWeek();
        $pronosticoAltaOrders = $allOrders->filter(function ($o) use ($startOfWeek) {
            $isEnviado = $o->core_status === CoreStatus::ENVIADO_AL_CLIENTE || $o->core_status?->value === 'ENVIADO AL CLIENTE';
            $sentAt = $o->last_sent_to_client_at ?? $o->updated_at;

            return $isEnviado && $sentAt && $sentAt->gte($startOfWeek);
        });

        $newTrelloQuery = Order::inBacklog()->newFromTrello()->prioritizeUrgente()->with(['designer', 'designers']);
        if ($this->selectedDesigner !== 'all') {
            $newTrelloQuery->where(function ($q) {
                $q->where('designer_id', $this->selectedDesigner)
                    ->orWhereHas('designers', fn ($d) => $d->where('designers.id', $this->selectedDesigner));
            });
        }
        $newTrelloOrders = $newTrelloQuery->get();

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
            'designers' => Designer::where('active', true)->internal()->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => __('Dashboard Operativo - ').config('app.name')]);
    }
}
