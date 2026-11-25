<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\BatchReadiness;
use App\Application\Delivery\Data\PhaseGroup;
use App\Application\Delivery\Data\ResolverGroup;
use App\Application\Delivery\Queries\Support\OverdueCutoff;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TodayQuery
{
    private const CARD = ['workOrder.client', 'workOrder.clientLocation', 'assignee', 'openHold'];

    private const LIST_CAP = 50;

    public function __construct(private ResolverQuery $resolver) {}

    /**
     * Every Today section the viewer is entitled to, keyed by variant name
     * (designer, manager, customer_service, reviewer).
     *
     * @return array<string, array<string, mixed>>
     */
    public function forUser(User $viewer, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $sections = [];

        if ($viewer->isDesigner()) {
            $sections['designer'] = $this->forDesigner($viewer, $now);
        }
        if ($viewer->isAdmin() || $viewer->isCoordinator()) {
            $sections['manager'] = $this->forManager($now);
        }
        if ($viewer->isCustomerService()) {
            $sections['customer_service'] = $this->forCustomerService($viewer, $now);
        }
        if ($viewer->is_reviewer) {
            $sections['reviewer'] = $this->forReviewer($viewer);
        }

        return $sections;
    }

    /**
     * No blocked cards, and nothing waiting on the client unless a follow-up alert is due.
     *
     * @return array{plannedToday: Collection<int, Deliverable>, carryOvers: Collection<int, Deliverable>, overdue: Collection<int, Deliverable>, alerts: Collection<int, Alert>, wip: array{used: int, limit: int}}
     */
    public function forDesigner(User $designer, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $today = $now->toDateString();

        $mine = fn (): Builder => Deliverable::query()->assignedTo($designer)->notBlocked()->with(self::CARD)
            ->orderByRaw('due_target IS NULL')->orderBy('due_target')->orderBy('id');

        $plannedToday = $mine()->inStatus(DeliverableStatus::IN_DESIGN)->plannedFor($today)->get();
        $carryOvers = $mine()->inStatus(DeliverableStatus::IN_DESIGN)->whereDate('planned_for', '<', $today)->get();

        $shown = $plannedToday->pluck('id')->merge($carryOvers->pluck('id'))->all();
        $overdue = $mine()
            ->whereNotIn('status', [DeliverableStatus::DONE->value, DeliverableStatus::CLIENT_REVIEW->value])
            ->whereDate('due_target', '<=', OverdueCutoff::lastOverdueDate($now))
            ->whereNotIn('id', $shown)
            ->get();

        $alerts = Alert::query()->open()
            ->where('user_id', $designer->getKey())
            ->whereDate('due_on', '<=', $today)
            ->whereHas('deliverable', fn (Builder $deliverables) => $deliverables->notBlocked())
            ->with(['deliverable.workOrder.client'])
            ->orderBy('due_on')
            ->get();

        return [
            'plannedToday' => $plannedToday,
            'carryOvers' => $carryOvers,
            'overdue' => $overdue,
            'alerts' => $alerts,
            'wip' => [
                'used' => Deliverable::query()->assignedTo($designer)->inStatus(DeliverableStatus::IN_DESIGN)->notBlocked()->count(),
                'limit' => (int) $designer->wip_limit,
            ],
        ];
    }

    /**
     * Deliverables in internal review grouped by work order and phase, with batch readiness.
     * Items assigned to another reviewer are left out.
     *
     * @return list<PhaseGroup>
     */
    public function forReviewer(User $reviewer): array
    {
        $workOrderIds = Deliverable::query()
            ->inStatus(DeliverableStatus::INTERNAL_REVIEW)
            ->where(fn (Builder $query) => $query->whereNull('reviewer_id')->orWhere('reviewer_id', $reviewer->getKey()))
            ->distinct()
            ->pluck('work_order_id');

        if ($workOrderIds->isEmpty()) {
            return [];
        }

        $all = Deliverable::query()
            ->whereIn('work_order_id', $workOrderIds)
            ->active()
            ->with(['workOrder.client', 'phase', 'assignee', 'approvals', 'openBlock', 'openHold'])
            ->orderBy('id')
            ->get();

        $groups = [];
        foreach ($all->groupBy(fn (Deliverable $deliverable): string => $deliverable->work_order_id.':'.($deliverable->phase_id ?? 0)) as $scope) {
            $inReview = $scope->contains(fn (Deliverable $deliverable): bool => $deliverable->status === DeliverableStatus::INTERNAL_REVIEW);
            if (! $inReview) {
                continue;
            }
            $phase = $scope->first()->phase;
            $groups[] = new PhaseGroup($phase instanceof Phase ? $phase : null, $scope->values(), BatchReadiness::evaluate($scope));
        }

        return $groups;
    }

    /**
     * What needs attention across the shop (admin and coordinator).
     *
     * @return array{overdue: Collection<int, Deliverable>, dueToday: Collection<int, Deliverable>, urgent: Collection<int, Deliverable>, unassigned: Collection<int, Deliverable>, blocked: Collection<int, Deliverable>, onHold: Collection<int, Deliverable>, alerts: Collection<int, Alert>, counts: array<string, int>}
     */
    public function forManager(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $today = $now->toDateString();
        $open = fn (): Builder => Deliverable::query()->active()->whereHas('workOrder', fn (Builder $workOrders) => $workOrders->where('in_backlog', false));
        $list = fn (Builder $query): Collection => $query->with([...self::CARD, 'openBlock'])
            ->orderByRaw('due_target IS NULL')->orderBy('due_target')->orderBy('id')->limit(self::LIST_CAP)->get();

        $overdueQuery = fn (): Builder => $open()->whereDate('due_target', '<=', OverdueCutoff::lastOverdueDate($now));
        $overdue = $list($overdueQuery());
        $dueToday = $list($open()->whereDate('due_target', $today)->whereNotIn('id', $overdueQuery()->select('orders.id')->toBase()));
        $urgent = $list($open()->whereJsonContains('flag_codes', DeliverableFlag::URGENT->value));
        $unassigned = $list($open()->inStatus(DeliverableStatus::READY)->whereNull('assignee_id')->notBlocked());
        $blocked = $list($open()->blocked());
        $onHold = $list($open()->onHold());

        $alerts = Alert::query()->open()->whereDate('due_on', '<=', $today)
            ->with(['deliverable.workOrder.client', 'recipient'])->orderBy('due_on')->limit(self::LIST_CAP)->get();

        return [
            'overdue' => $overdue,
            'dueToday' => $dueToday,
            'urgent' => $urgent,
            'unassigned' => $unassigned,
            'blocked' => $blocked,
            'onHold' => $onHold,
            'alerts' => $alerts,
            'counts' => [
                'overdue' => $overdueQuery()->count(),
                'urgent' => $open()->whereJsonContains('flag_codes', DeliverableFlag::URGENT->value)->count(),
                'unassigned' => $open()->inStatus(DeliverableStatus::READY)->whereNull('assignee_id')->notBlocked()->count(),
                'blocked' => $open()->blocked()->count(),
                'onHold' => $open()->onHold()->count(),
            ],
        ];
    }

    /**
     * Open blocks and holds, as in the Resolver, for the customer-service user.
     *
     * @return array{groups: list<ResolverGroup>}
     */
    public function forCustomerService(User $viewer, ?CarbonInterface $now = null): array
    {
        return ['groups' => $this->resolver->run($viewer->getKey(), $now)];
    }
}
