<?php

namespace App\Livewire\Dashboard;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\Substatus as SubstatusModel;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Analytics extends Component
{
    public function render()
    {
        // Executive KPIs (Active Workspace Design Orders - Excludes Backlog & En Producción)
        $totalOrders = Order::activeInWorkspace()->count();
        $inProductionCount = Order::inWorkspace()->where('core_status', CoreStatus::EN_PRODUCCION)->count();
        $overdueCount = Order::activeInWorkspace()->where('substatus', Substatus::OVERDUE)->count();
        $approvedCount = Order::activeInWorkspace()->where('approved', true)->count();
        $doneTodayCount = Order::activeInWorkspace()->where('done_today', true)->count();

        $overdueRate = $totalOrders > 0 ? round(($overdueCount / $totalOrders) * 100, 1) : 0;
        $slaComplianceRate = 100 - $overdueRate;

        $totalClientRevisions = (int) Order::activeInWorkspace()->sum('client_revision_count');
        $totalInternalRevisions = (int) Order::activeInWorkspace()->sum('internal_revision_count');
        $avgClientRevisions = $totalOrders > 0 ? round($totalClientRevisions / $totalOrders, 1) : 0;

        // Core Status Distribution (Active Design Stages Only - Excludes En Producción)
        $coreStatusCounts = [];
        foreach (CoreStatus::cases() as $status) {
            if ($status === CoreStatus::EN_PRODUCCION) {
                continue;
            }
            $count = Order::activeInWorkspace()->where('core_status', $status)->count();
            $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
            $coreStatusCounts[] = [
                'status' => $status,
                'label' => $status->label(),
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        // Substatus Frequency Distribution (Active Workspace Orders)
        $substatusCounts = [];
        $allSubstatuses = SubstatusModel::where('active', true)->orderBy('sort_order')->get();
        foreach ($allSubstatuses as $sub) {
            $count = Order::activeInWorkspace()->where('substatus', $sub->name)->count();
            $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
            $substatusCounts[] = [
                'substatus' => $sub,
                'name' => $sub->name,
                'style' => $sub->inline_style,
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        // Designer Workload & Performance Analysis (Active Workspace Orders)
        $designers = Designer::where('active', true)->get();
        $designerStats = [];
        foreach ($designers as $des) {
            $assignedQuery = Order::activeInWorkspace()
                ->where(function ($q) use ($des) {
                    $q->where('designer_id', $des->id)
                        ->orWhereHas('designers', fn ($dq) => $dq->where('designers.id', $des->id));
                });

            $assignedCount = (clone $assignedQuery)->count();
            $desOverdue = (clone $assignedQuery)->where('substatus', Substatus::OVERDUE)->count();
            $desClientRev = (int) (clone $assignedQuery)->sum('client_revision_count');
            $desWorkloadPct = $totalOrders > 0 ? round(($assignedCount / $totalOrders) * 100, 1) : 0;

            $designerStats[] = [
                'designer' => $des,
                'count' => $assignedCount,
                'overdue' => $desOverdue,
                'client_revisions' => $desClientRev,
                'workload_pct' => $desWorkloadPct,
            ];
        }

        $unassignedCount = Order::activeInWorkspace()
            ->whereNull('designer_id')
            ->whereDoesntHave('designers')
            ->count();

        // Top Accounts by Active Order Volume (Active Workspace Orders)
        $topCompanies = Order::activeInWorkspace()
            ->select('company_name', DB::raw('count(*) as total_orders'), DB::raw('sum(case when substatus = "OVERDUE" then 1 else 0 end) as overdue_count'))
            ->whereNotNull('company_name')
            ->where('company_name', '!=', '')
            ->groupBy('company_name')
            ->orderByDesc('total_orders')
            ->limit(8)
            ->get();

        // Related Tasks Metrics (Distinct from Orders)
        $totalRelatedTasks = RelatedTask::whereHas('order', fn ($q) => $q->activeInWorkspace())->count();
        $pendingRelatedTasks = RelatedTask::whereHas('order', fn ($q) => $q->activeInWorkspace())->where('status', '!=', 'done')->count();
        $completedRelatedTasks = RelatedTask::whereHas('order', fn ($q) => $q->activeInWorkspace())->where('status', 'done')->count();

        return view('livewire.dashboard.analytics', [
            'totalOrders' => $totalOrders,
            'inProductionCount' => $inProductionCount,
            'overdueCount' => $overdueCount,
            'overdueRate' => $overdueRate,
            'slaComplianceRate' => $slaComplianceRate,
            'approvedCount' => $approvedCount,
            'doneTodayCount' => $doneTodayCount,
            'totalClientRevisions' => $totalClientRevisions,
            'totalInternalRevisions' => $totalInternalRevisions,
            'avgClientRevisions' => $avgClientRevisions,
            'coreStatusCounts' => $coreStatusCounts,
            'substatusCounts' => $substatusCounts,
            'designerStats' => $designerStats,
            'unassignedCount' => $unassignedCount,
            'topCompanies' => $topCompanies,
            'totalRelatedTasks' => $totalRelatedTasks,
            'pendingRelatedTasks' => $pendingRelatedTasks,
            'completedRelatedTasks' => $completedRelatedTasks,
        ])->layout('components.layouts.app', ['title' => 'Analytics Dashboard - Kudos Design Ops']);
    }
}
