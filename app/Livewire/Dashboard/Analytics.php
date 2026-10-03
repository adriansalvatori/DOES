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
        $designerAvailabilityStats = [];

        // Internal team designers only for Availability graphic (excluding external)
        $internalDesigners = Designer::where('active', true)->internal()->get();

        // Core statuses defining immediate designer availability & active working load
        $availableCoreStatuses = [
            CoreStatus::EURALIZ_ORDERS_RECEIVED,
            CoreStatus::ADRIAN_ORDERS_RECEIVED,
            CoreStatus::CESAR_ORDERS_RECEIVED,
            CoreStatus::TO_DO_TODAY,
            CoreStatus::ENVIADO_A_CAMILA,
        ];

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

        foreach ($internalDesigners as $des) {
            $assignedOrders = $activeOrders->filter(function (Order $o) use ($des): bool {
                return $o->designer_id === $des->id
                    || $o->designers->contains('id', $des->id);
            });

            // Availability stats: Only count ENTRANTE (queue), WORKING TODAY, SENT TO CAMILA
            $availableOrders = $assignedOrders->filter(fn (Order $o): bool => in_array($o->core_status, $availableCoreStatuses, true));
            $availableCount = $availableOrders->count();

            $incomingQueueCount = $availableOrders->filter(fn (Order $o): bool => in_array($o->core_status, CoreStatus::designerQueueStatuses(), true))->count();
            $workingTodayCount = $availableOrders->where('core_status', CoreStatus::TO_DO_TODAY)->count();
            $sentToCamilaCount = $availableOrders->where('core_status', CoreStatus::ENVIADO_A_CAMILA)->count();

            $queueStatus = $des->getQueueStatus();

            $designerAvailabilityStats[] = [
                'designer' => $des,
                'queue_status' => $queueStatus,
                'queue_color' => $queueStatus->hexColor(),
                'total_active' => $availableCount,
                'incoming_count' => $incomingQueueCount,
                'working_today_count' => $workingTodayCount,
                'sent_to_camila_count' => $sentToCamilaCount,
                'orders' => $availableOrders->values(),
            ];
        }

        $maxAvailableOrders = max(array_column($designerAvailabilityStats, 'total_active') ?: [0]);
        if ($maxAvailableOrders < 1) {
            $maxAvailableOrders = 1;
        }

        $minAvailableOrders = min(array_column($designerAvailabilityStats, 'total_active') ?: [0]);

        $unassignedCount = $activeOrders->filter(function (Order $o): bool {
            return is_null($o->designer_id) && $o->designers->isEmpty();
        })->count();

        $unassignedAvailableCount = $activeOrders->filter(function (Order $o) use ($availableCoreStatuses): bool {
            return is_null($o->designer_id)
                && $o->designers->isEmpty()
                && in_array($o->core_status, $availableCoreStatuses, true);
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
            'designerAvailabilityStats' => $designerAvailabilityStats,
            'workingTodayHex' => CoreStatus::TO_DO_TODAY->hexColor(),
            'sentToCamilaHex' => CoreStatus::ENVIADO_A_CAMILA->hexColor(),
            'maxAvailableOrders' => $maxAvailableOrders,
            'minAvailableOrders' => $minAvailableOrders,
            'unassignedCount' => $unassignedCount,
            'unassignedAvailableCount' => $unassignedAvailableCount,
        ])->layout('components.layouts.app', ['title' => __('Analytics Dashboard - ').config('app.name')]);
    }
}
