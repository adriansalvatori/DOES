<?php

namespace App\Application\Delivery\Queries\Support;

use App\Application\Delivery\Data\WorkOrderTab;
use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * SQL that classifies a work order into a tab by its earliest unfinished deliverable:
 * design work pending -> active, else production, else fulfillment; nothing open -> done.
 * A work order without deliverables is active.
 */
final class WorkOrderTabExpression
{
    public static function sql(): string
    {
        $positions = collect(DeliverableStatus::cases())
            ->map(fn (DeliverableStatus $status): string => "WHEN '{$status->value}' THEN {$status->position()}")
            ->implode(' ');

        $lastDesign = collect(DeliverableStatus::inArea(Area::DESIGN))->last()->position();
        $lastProduction = collect(DeliverableStatus::inArea(Area::PRODUCTION))->last()->position();
        $done = DeliverableStatus::DONE->value;

        $scope = 'd.work_order_id = work_orders.id AND d.deleted_at IS NULL';
        $total = "(SELECT COUNT(*) FROM orders d WHERE {$scope})";
        $openMin = "(SELECT MIN(CASE COALESCE(d.status, 'intake') {$positions} END) FROM orders d WHERE {$scope} AND COALESCE(d.status, 'intake') <> '{$done}')";

        return "CASE WHEN {$total} = 0 THEN 'active' WHEN {$openMin} IS NULL THEN 'done' WHEN {$openMin} <= {$lastDesign} THEN 'active' WHEN {$openMin} <= {$lastProduction} THEN 'production' ELSE 'fulfillment' END";
    }

    /**
     * @param  Builder<WorkOrder>  $query
     * @return Builder<WorkOrder>
     */
    public static function whereTab(Builder $query, WorkOrderTab $tab): Builder
    {
        return $query->whereRaw('('.self::sql().') = ?', [$tab->value]);
    }
}
