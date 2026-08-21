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

    public function mount()
    {
        $this->selectedWeekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
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
                'date' => $dayDate,
                'date_string' => $dayDate->toDateString(),
            ];
        }

        $designerQuery = Designer::where('active', true)->with(['orders' => fn($q) => $q->inWorkspace()]);
        if ($this->selectedDesignerFilter !== 'all') {
            $designerQuery->where('id', $this->selectedDesignerFilter);
        }
        $designers = $designerQuery->get();

        $unscheduledOrders = Order::inWorkspace()
            ->whereNull('scheduled_date')
            ->whereNotIn('core_status', [\App\Enums\CoreStatus::EN_PRODUCCION, \App\Enums\CoreStatus::ON_HOLD])
            ->get();

        return view('livewire.planner.weekly-planner', [
            'days' => $days,
            'designers' => $designers,
            'allDesigners' => Designer::where('active', true)->get(),
            'unscheduledOrders' => $unscheduledOrders,
        ])->layout('components.layouts.app', ['title' => 'Planificador Semanal - Kudos Design Ops']);
    }
}
