<?php

namespace App\Domain\Delivery\Support;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Scheduling\BusinessCalendar;
use App\Domain\Scheduling\FollowUpPolicy;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Opens and closes the alerts people act on (follow-ups, welcome email, delay notice).
 */
class AlertScheduler
{
    public function __construct(
        private BusinessCalendar $calendar,
        private FollowUpPolicy $followUps,
        private BlockOwnerResolver $owners,
    ) {}

    /**
     * Restarts the 3/6/9 business day follow-ups counted from the moment the proof was sent.
     */
    public function scheduleClientFollowUps(Deliverable $deliverable, CarbonInterface $sentAt): void
    {
        $this->resolve($deliverable, $this->followUps->kinds());

        foreach ($this->followUps->kinds() as $kind) {
            $this->open($deliverable, $kind, $this->followUps->dueOn($kind, $sentAt)->toDateString(), $deliverable->assignee_id);
        }
    }

    /**
     * The reviewer is reminded the next business day, or today when the deliverable is already due.
     */
    public function scheduleReviewerFollowUp(Deliverable $deliverable, bool $urgent): void
    {
        $this->resolve($deliverable, [AlertKind::REVIEWER_FOLLOW_UP]);

        $dueOn = $urgent ? now()->toDateString() : $this->calendar->nextBusinessDay(now())->toDateString();
        $recipient = $deliverable->reviewer_id ?? $this->owners->defaultReviewer()?->getKey();

        $this->open($deliverable, AlertKind::REVIEWER_FOLLOW_UP, $dueOn, $recipient);
    }

    public function scheduleWelcomeEmail(WorkOrder $workOrder, ?User $recipient, ?CarbonInterface $entry = null): Alert
    {
        return Alert::create([
            'work_order_id' => $workOrder->getKey(),
            'kind' => AlertKind::WELCOME_EMAIL,
            'due_on' => $this->calendar->welcomeEmailDate($entry)->toDateString(),
            'user_id' => $recipient?->getKey(),
        ]);
    }

    public function ensureDelayNotice(Deliverable $deliverable): ?Alert
    {
        $exists = Alert::open()->where('deliverable_id', $deliverable->getKey())->where('kind', AlertKind::DELAY_NOTICE->value)->exists();
        if ($exists) {
            return null;
        }

        return $this->open($deliverable, AlertKind::DELAY_NOTICE, now()->toDateString(), $deliverable->assignee_id);
    }

    /**
     * Opens the alert unless an open one of that kind and due date already exists.
     */
    public function open(Deliverable $deliverable, AlertKind $kind, string $dueOn, ?int $recipientId): Alert
    {
        return Alert::open()
            ->where('deliverable_id', $deliverable->getKey())
            ->where('kind', $kind->value)
            ->whereDate('due_on', $dueOn)
            ->first()
            ?? Alert::create([
                'deliverable_id' => $deliverable->getKey(),
                'kind' => $kind,
                'due_on' => $dueOn,
                'user_id' => $recipientId,
            ]);
    }

    /**
     * @param  list<AlertKind>  $kinds
     */
    public function resolve(Deliverable $deliverable, array $kinds): int
    {
        return Alert::open()
            ->where('deliverable_id', $deliverable->getKey())
            ->whereIn('kind', array_map(fn (AlertKind $kind): string => $kind->value, $kinds))
            ->update(['resolved_at' => now()]);
    }

    public function resolveAll(Deliverable $deliverable): int
    {
        return Alert::open()->where('deliverable_id', $deliverable->getKey())->update(['resolved_at' => now()]);
    }

    /**
     * Alerts that belong to the stage a deliverable just left.
     *
     * @return list<AlertKind>
     */
    public function clientKinds(): array
    {
        return [...$this->followUps->kinds(), AlertKind::DELAY_NOTICE, AlertKind::WELCOME_EMAIL];
    }
}
