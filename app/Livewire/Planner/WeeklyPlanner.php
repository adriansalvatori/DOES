<?php

namespace App\Livewire\Planner;

use App\Models\Designer;
use App\Models\Order;
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
        $this->viewMonth = Carbon::parse($this->viewMonth . '-01')->subMonth()->format('Y-m');
    }

    public function nextMonth()
    {
        $this->viewMonth = Carbon::parse($this->viewMonth . '-01')->addMonth()->format('Y-m');
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
        $viewDate = Carbon::parse($this->viewMonth . '-01');
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
            'core_status' => \App\Enums\CoreStatus::TO_DO_TODAY,
        ]);

        if ($willBeOverdue) {
            session()->flash('warning', "Atención: Programar la orden {$order->company_name} para el {$scheduledDate->format('d M')} supera la fecha límite ({$order->current_due_date->format('d M')}).");
        } else {
            session()->flash('message', "Orden {$order->company_name} programada para el {$scheduledDate->format('d M')}.");
        }
    }

    public function unscheduleOrder($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update([
            'scheduled_date' => null,
        ]);
        session()->flash('message', "Orden {$order->company_name} quitada de la agenda.");
    }

    public function render()
    {
        $startDate = Carbon::parse($this->selectedWeekStart);
        $days = [];

        for ($i = 0; $i < 5; $i++) {
            $dayDate = $startDate->copy()->addDays($i);
            $days[] = [
                'day_name' => match($i) {
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
            'range_label' => $nextWeekMonday->format('d M') . ' - ' . $nextWeekFriday->format('d M'),
        ];

        $designerQuery = Designer::where('active', true)->with(['orders' => fn($q) => $q->inWorkspace()]);
        if ($this->selectedDesignerFilter !== 'all') {
            $designerQuery->where('id', $this->selectedDesignerFilter);
        }
        $designers = $designerQuery->get();

        $unscheduledOrders = Order::inWorkspace()
            ->whereNull('scheduled_date')
            ->whereNotIn('core_status', [\App\Enums\CoreStatus::EN_PRODUCCION, \App\Enums\CoreStatus::ON_HOLD])
            ->get();

        $daysWithNextWeek = array_merge($days, [$nextWeekItem]);

        return view('livewire.planner.weekly-planner', [
            'days' => $daysWithNextWeek,
            'weekDaysOnly' => $days,
            'nextWeekItem' => $nextWeekItem,
            'designers' => $designers,
            'allDesigners' => Designer::where('active', true)->get(),
            'unscheduledOrders' => $unscheduledOrders,
        ])->layout('components.layouts.app', ['title' => 'Planificador Semanal - Kudos Design Ops']);
    }
}
