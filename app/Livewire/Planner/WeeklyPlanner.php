<?php

namespace App\Livewire\Planner;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Models\SubtaskPreset;
use Carbon\Carbon;
use Livewire\Component;

class WeeklyPlanner extends Component
{
    public $selectedWeekStart;

    public $selectedDesignerFilter = 'all';

    public $viewMonth;

    public function mount()
    {
        $this->selectedWeekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->viewMonth = now()->format('Y-m');
    }

    public function previousWeek()
    {
        $this->selectedWeekStart = Carbon::parse($this->selectedWeekStart)->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function nextWeek()
    {
        $this->selectedWeekStart = Carbon::parse($this->selectedWeekStart)->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function thisWeek()
    {
        $this->selectedWeekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function jumpWeeks($weeks)
    {
        $this->selectedWeekStart = now()->addWeeks($weeks)->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function selectWeekFromDate($dateString)
    {
        $this->selectedWeekStart = Carbon::parse($dateString)->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function previousMonth()
    {
        $this->viewMonth = Carbon::parse($this->viewMonth.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth()
    {
        $this->viewMonth = Carbon::parse($this->viewMonth.'-01')->addMonth()->format('Y-m');
    }

    protected function syncViewMonth()
    {
        $this->viewMonth = Carbon::parse($this->selectedWeekStart)->format('Y-m');
    }

    public function updatedSelectedWeekStart($value)
    {
        if ($value) {
            $this->selectedWeekStart = Carbon::parse($value)->startOfWeek(Carbon::MONDAY)->toDateString();
            $this->syncViewMonth();
        }
    }

    public function getMiniCalendarDaysProperty()
    {
        $viewDate = Carbon::parse($this->viewMonth.'-01');
        $startOfGrid = $viewDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $endOfGrid = $viewDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $selectedMonday = Carbon::parse($this->selectedWeekStart)->startOfWeek(Carbon::MONDAY)->toDateString();

        $grid = [];
        $current = $startOfGrid->copy();
        while ($current->lte($endOfGrid)) {
            $weekMonday = $current->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $grid[] = [
                'date' => $current->copy(),
                'date_string' => $current->toDateString(),
                'day_number' => $current->day,
                'is_current_month' => $current->month === $viewDate->month,
                'is_today' => $current->isToday(),
                'is_selected_week' => $weekMonday === $selectedMonday,
                'week_monday' => $weekMonday,
            ];
            $current->addDay();
        }

        return collect($grid);
    }

    public function scheduleOrder($orderId, $dateString)
    {
        $order = Order::findOrFail($orderId);
        $scheduledDate = Carbon::parse($dateString);

        $willBeOverdue = false;
        if ($order->current_due_date && $scheduledDate->isAfter($order->current_due_date)) {
            $willBeOverdue = true;
        }

        $order->update([
            'scheduled_date' => $scheduledDate->toDateString(),
            'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        if ($willBeOverdue) {
            session()->flash('warning', "Atención: Programar la orden {$order->company_name} para el {$scheduledDate->format('d M')} supera la fecha límite ({$order->current_due_date->format('d M')}).");
        } else {
            session()->flash('message', "Orden {$order->company_name} programada para el {$scheduledDate->format('d M')}.");
        }
    }

    public function scheduleSubtask($orderId, $title, $dateString, $designerId = null)
    {
        $order = Order::findOrFail($orderId);
        $scheduledDate = Carbon::parse($dateString);
        $assigneeId = $designerId ?: ($order->designer_id ?? $order->designers->first()?->id);

        $subtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => trim($title),
            'type' => RelatedTaskType::SUBTASK->value,
            'scheduled_date' => $scheduledDate->toDateString(),
            'assignee_id' => $assigneeId,
            'status' => 'todo',
            'priority' => 'normal',
        ]);

        // Keep order scheduled_date synced to most recent scheduled date
        $order->update([
            'scheduled_date' => $scheduledDate->toDateString(),
            'in_workspace' => true,
        ]);

        // Log OrderEvent for timeline
        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'SUBTASK_SCHEDULED',
            'actor' => auth()->user()?->name ?? 'Diseñador',
            'new_value' => $title,
            'metadata' => [
                'task_id' => $subtask->id,
                'task_title' => $title,
                'date' => $scheduledDate->toDateString(),
            ],
        ]);

        session()->flash('message', "Subtarea \"{$title}\" programada para {$order->company_name} el {$scheduledDate->format('d M')}.");
        $this->dispatch('order-updated');
    }

    public function toggleSubtaskComplete($taskId)
    {
        $subtask = RelatedTask::with('order')->findOrFail($taskId);
        $newStatus = $subtask->status === 'done' ? 'todo' : 'done';

        $subtask->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'done' ? now() : null,
        ]);

        if ($subtask->order && $newStatus === 'done') {
            OrderEvent::create([
                'order_id' => $subtask->order->id,
                'event_type' => 'SUBTASK_COMPLETED',
                'actor' => auth()->user()?->name ?? 'Diseñador',
                'new_value' => $subtask->title,
                'metadata' => [
                    'task_id' => $subtask->id,
                    'task_title' => $subtask->title,
                ],
            ]);
        }

        $this->dispatch('order-updated');
    }

    public function deleteSubtask($taskId)
    {
        $subtask = RelatedTask::findOrFail($taskId);
        $subtask->delete();
        session()->flash('message', 'Subtarea eliminada.');
        $this->dispatch('order-updated');
    }

    public function unscheduleOrder($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'scheduled_date' => null,
        ]);
        session()->flash('message', "Orden {$order->company_name} quitada de la agenda.");
    }

    public function toggleDoneToday($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update(['done_today' => ! $order->done_today]);
        $this->dispatch('order-updated');
    }

    public function render()
    {
        $startDate = Carbon::parse($this->selectedWeekStart);
        $days = [];

        for ($i = 0; $i < 5; $i++) {
            $dayDate = $startDate->copy()->addDays($i);
            $days[] = [
                'day_name' => match ($i) {
                    0 => 'Lunes',
                    1 => 'Martes',
                    2 => 'Miércoles',
                    3 => 'Jueves',
                    4 => 'Viernes',
                },
                'is_next_week' => false,
                'date' => $dayDate,
                'date_string' => $dayDate->toDateString(),
            ];
        }

        $nextWeekMonday = $startDate->copy()->addWeek()->startOfWeek(Carbon::MONDAY);
        $nextWeekFriday = $nextWeekMonday->copy()->addDays(4);

        $nextWeekItem = [
            'day_name' => 'Next Week',
            'is_next_week' => true,
            'date' => $nextWeekMonday,
            'date_string' => $nextWeekMonday->toDateString(),
            'range_label' => $nextWeekMonday->format('d M').' - '.$nextWeekFriday->format('d M'),
        ];

        $designerQuery = Designer::where('active', true)->with(['orders' => fn ($q) => $q->inWorkspace()->prioritizeUrgente()->with(['designers', 'designer', 'relatedTasks'])]);
        if ($this->selectedDesignerFilter !== 'all') {
            $designerQuery->where('id', $this->selectedDesignerFilter);
        }
        $designers = $designerQuery->get();

        $unscheduledOrders = Order::inWorkspace()
            ->prioritizeUrgente()
            ->whereNull('scheduled_date')
            ->whereNotIn('core_status', [CoreStatus::EN_PRODUCCION, CoreStatus::ON_HOLD])
            ->get();

        $daysWithNextWeek = array_merge($days, [$nextWeekItem]);

        return view('livewire.planner.weekly-planner', [
            'days' => $daysWithNextWeek,
            'weekDaysOnly' => $days,
            'nextWeekItem' => $nextWeekItem,
            'designers' => $designers,
            'allDesigners' => Designer::where('active', true)->get(),
            'unscheduledOrders' => $unscheduledOrders,
            'subtaskPresets' => SubtaskPreset::where('is_active', true)->orderBy('sort_order')->get(),
        ])->layout('components.layouts.app', ['title' => 'Planificador Semanal - Kudos Design Ops']);
    }
}
