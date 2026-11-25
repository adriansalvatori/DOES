<?php

namespace App\Domain\Scheduling;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Due dates and their computed health.
 */
class SlaPolicy
{
    public const NEW_WORK_DAYS = 3;

    public const CLIENT_CHANGES_DAYS = 2;

    public const RESUME_DAYS = 2;

    public const APPROVAL_DAYS = 1;

    public const DELAY_NOTICE_FROM = '14:30';

    public const OVERDUE_FROM = '16:00';

    public function __construct(private BusinessCalendar $calendar) {}

    public function newWorkDueTarget(?CarbonInterface $from = null): CarbonImmutable
    {
        return $this->calendar->addBusinessDays($this->calendar->rollForward($from ?? now()), self::NEW_WORK_DAYS);
    }

    public function clientChangesDueTarget(?CarbonInterface $from = null): CarbonImmutable
    {
        return $this->calendar->addBusinessDays($from ?? now(), self::CLIENT_CHANGES_DAYS);
    }

    public function resumeDueTarget(?CarbonInterface $from = null): CarbonImmutable
    {
        return $this->calendar->addBusinessDays($from ?? now(), self::RESUME_DAYS);
    }

    public function approvalDueTarget(bool $urgent, ?CarbonInterface $from = null): CarbonImmutable
    {
        $day = CarbonImmutable::instance($from ?? now())->startOfDay();

        return $urgent ? $day : $this->calendar->addBusinessDays($day, self::APPROVAL_DAYS);
    }

    /**
     * Statuses whose due date is tracked. Client review, production and done are not.
     *
     * @return list<DeliverableStatus>
     */
    public function trackedStatuses(): array
    {
        return [
            DeliverableStatus::INTAKE,
            DeliverableStatus::READY,
            DeliverableStatus::IN_DESIGN,
            DeliverableStatus::INTERNAL_REVIEW,
            DeliverableStatus::PRODUCTION_FILES,
        ];
    }

    /**
     * Statuses where a late delivery has to be explained to the client.
     *
     * @return list<DeliverableStatus>
     */
    public function delayNoticeStatuses(): array
    {
        return [DeliverableStatus::READY, DeliverableStatus::IN_DESIGN, DeliverableStatus::INTERNAL_REVIEW];
    }

    public function isTracked(Deliverable $deliverable, ?bool $onHold = null): bool
    {
        if (! in_array($deliverable->status, $this->trackedStatuses(), true)) {
            return false;
        }

        return ! ($onHold ?? $deliverable->isOnHold());
    }

    public function state(?CarbonInterface $dueTarget, ?CarbonInterface $now = null): SlaState
    {
        if ($dueTarget === null) {
            return SlaState::ON_TRACK;
        }

        $now = CarbonImmutable::instance($now ?? now());
        $due = CarbonImmutable::instance($dueTarget)->startOfDay();
        $today = $now->startOfDay();

        if ($due->lt($today)) {
            return SlaState::OVERDUE;
        }

        if ($due->equalTo($today)) {
            return $now->format('H:i') >= self::OVERDUE_FROM ? SlaState::OVERDUE : SlaState::ALMOST_OVERDUE;
        }

        return SlaState::ON_TRACK;
    }

    public function stateFor(Deliverable $deliverable, ?CarbonInterface $now = null): SlaState
    {
        if (! $this->isTracked($deliverable)) {
            return SlaState::ON_TRACK;
        }

        return $this->state($deliverable->due_target, $now);
    }

    /**
     * Whether the client has to be warned: late already, or due today from 14:30.
     */
    public function needsDelayNotice(Deliverable $deliverable, ?CarbonInterface $now = null): bool
    {
        if (! in_array($deliverable->status, $this->delayNoticeStatuses(), true) || $deliverable->due_target === null) {
            return false;
        }

        if ($deliverable->isOnHold()) {
            return false;
        }

        $now = CarbonImmutable::instance($now ?? now());
        $due = CarbonImmutable::instance($deliverable->due_target)->startOfDay();

        if ($due->lt($now->startOfDay())) {
            return true;
        }

        return $due->equalTo($now->startOfDay()) && $now->format('H:i') >= self::DELAY_NOTICE_FROM;
    }
}
