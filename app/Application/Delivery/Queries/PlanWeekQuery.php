<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\PlanWeek;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PlanWeekQuery
{
    public const BLOCKED_BACKLOG_CAP = 30;

    private const CARD = ['workOrder.client', 'assignee', 'openBlock'];

    /**
     * The Ready backlog (unplanned, not blocked) by work order promised date, plus the assignee x Monday-Friday grid.
     * WIP usage per cell counts planned cards that are not blocked.
     */
    public function run(CarbonInterface $anyDayOfWeek): PlanWeek
    {
        $monday = CarbonImmutable::instance($anyDayOfWeek)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $days = array_map(fn (int $offset): CarbonImmutable => $monday->addDays($offset), range(0, 4));

        $backlog = Deliverable::query()
            ->inStatus(DeliverableStatus::READY)
            ->whereNull('planned_for')
            ->notBlocked()
            ->inWorkspace()
            ->leftJoin('work_orders', 'work_orders.id', '=', 'orders.work_order_id')
            ->select('orders.*')
            ->orderByRaw('work_orders.promised_date IS NULL')
            ->orderBy('work_orders.promised_date')
            ->orderBy('orders.id')
            ->with(self::CARD)
            ->get();

        $blockedBacklog = Deliverable::query()
            ->inWorkspace()
            ->inStatus(DeliverableStatus::READY)
            ->whereNull('planned_for')
            ->blocked()
            ->with(['workOrder.client', 'openBlock'])
            ->orderBy('id')
            ->limit(self::BLOCKED_BACKLOG_CAP)
            ->get();

        $planned = Deliverable::query()
            ->inWorkspace()
            ->inStatus([DeliverableStatus::READY, DeliverableStatus::IN_DESIGN])
            ->whereNotNull('assignee_id')
            ->whereBetween('planned_for', [$days[0]->toDateString(), $days[4]->toDateString()])
            ->with(self::CARD)
            ->orderBy('id')
            ->get();

        $designers = User::query()
            ->designTeam()
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($designers as $designer) {
            $cells = [];
            foreach ($days as $day) {
                $cards = $planned
                    ->filter(fn (Deliverable $deliverable): bool => $deliverable->assignee_id === $designer->getKey()
                        && $deliverable->planned_for->toDateString() === $day->toDateString())
                    ->values();

                $cells[$day->toDateString()] = [
                    'cards' => $cards,
                    'used' => $cards->filter(fn (Deliverable $deliverable): bool => $deliverable->openBlock === null)->count(),
                ];
            }

            $rows[] = ['assignee' => $designer, 'limit' => (int) $designer->wip_limit, 'cells' => $cells];
        }

        return new PlanWeek($monday, $days, $backlog, $rows, $blockedBacklog);
    }
}
