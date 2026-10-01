<?php

namespace App\Livewire\Dashboard;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Analytics extends Component
{
    public function mount(): mixed
    {
        $user = Auth::user();
        if ($user && $user->isDesigner()) {
            return redirect()->route('kanban');
        }

        return null;
    }

    public int $totalOrders = 0;

    public function render()
    {
        // Fetch all active workspace design orders in a single optimized query
        $activeOrders = Order::activeInWorkspace()
            ->with(['designer', 'designers'])
            ->get();

        $this->totalOrders = $activeOrders->count();
        $totalOrders = $this->totalOrders;

        // Supporting counts
        $inProductionCount = Order::inWorkspace()
            ->where('core_status', CoreStatus::EN_PRODUCCION)
            ->count();
        $approvedCount = $activeOrders->where('approved', true)->count();
        $doneTodayCount = $activeOrders->where('done_today', true)->count();

        // Accurate SLA and Overdue calculation using domain logic
        $overdueCount = $activeOrders->filter(fn (Order $order): bool => $order->isOverdue())->count();
        $overdueRate = $totalOrders > 0 ? round(($overdueCount / $totalOrders) * 100, 1) : 0;
        $slaComplianceRate = $totalOrders > 0 ? round((($totalOrders - $overdueCount) / $totalOrders) * 100, 1) : 100.0;

        // Core Status Distribution (Active Design Stages Only)
        $coreStatusCounts = [];
        $activeStatuses = [
            CoreStatus::ENTRANTE,
            CoreStatus::EURALIZ_ORDERS_RECEIVED,
            CoreStatus::ADRIAN_ORDERS_RECEIVED,
            CoreStatus::CESAR_ORDERS_RECEIVED,
            CoreStatus::TO_DO_TODAY,
            CoreStatus::ENVIADO_A_CAMILA,
            CoreStatus::ENVIADO_AL_CLIENTE,
            CoreStatus::ON_HOLD,
        ];

        foreach ($activeStatuses as $status) {
            $count = $activeOrders->where('core_status', $status)->count();
            $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
            $coreStatusCounts[] = [
                'status' => $status,
                'label' => $status->label(),
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        // Designer Workload & Performance (Active Workspace Orders)
        $designers = Designer::where('active', true)->get();
        $designerStats = [];
        foreach ($designers as $des) {
            $assignedOrders = $activeOrders->filter(function (Order $o) use ($des): bool {
                return $o->designer_id === $des->id
                    || $o->designers->contains('id', $des->id);
            });

            $assignedCount = $assignedOrders->count();
            $desOverdue = $assignedOrders->filter(fn (Order $o): bool => $o->isOverdue())->count();
            $desWorkloadPct = $totalOrders > 0 ? round(($assignedCount / $totalOrders) * 100, 1) : 0;

            $designerStats[] = [
                'designer' => $des,
                'count' => $assignedCount,
                'overdue' => $desOverdue,
                'workload_pct' => $desWorkloadPct,
            ];
        }

        $unassignedCount = $activeOrders->filter(function (Order $o): bool {
            return is_null($o->designer_id) && $o->designers->isEmpty();
        })->count();

        return view('livewire.dashboard.analytics', [
            'totalOrders' => $totalOrders,
            'inProductionCount' => $inProductionCount,
            'approvedCount' => $approvedCount,
            'doneTodayCount' => $doneTodayCount,
            'overdueCount' => $overdueCount,
            'overdueRate' => $overdueRate,
            'slaComplianceRate' => $slaComplianceRate,
            'coreStatusCounts' => $coreStatusCounts,
            'designerStats' => $designerStats,
            'unassignedCount' => $unassignedCount,
        ])->layout('components.layouts.app', ['title' => __('Analytics Dashboard - ').config('app.name')]);
    }
}
