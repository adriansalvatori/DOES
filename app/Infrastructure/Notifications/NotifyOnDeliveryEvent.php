<?php

namespace App\Infrastructure\Notifications;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Events\BlockResolved;
use App\Domain\Delivery\Events\DeliverableBlocked;
use App\Domain\Delivery\Events\DeliverableDueToday;
use App\Domain\Delivery\Events\DeliverableMoved;
use App\Domain\Delivery\Events\DeliverableOverdue;
use App\Domain\Delivery\Events\DeliverablePutOnHold;
use App\Domain\Delivery\Events\DeliverableResumed;
use App\Domain\Delivery\Events\DeliverableUrgent;
use App\Domain\Delivery\Events\WorkOrderCreated;
use App\Domain\Delivery\Models\Deliverable;
use App\Enums\CoreStatus;
use App\Enums\UserRole;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Maps delivery events to the notification types the bell already understands, with the existing recipient rules.
 * A block also reaches its owner, who is usually customer service.
 */
class NotifyOnDeliveryEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    /**
     * @return list<class-string>
     */
    public static function events(): array
    {
        return [
            DeliverableMoved::class,
            DeliverableBlocked::class,
            BlockResolved::class,
            DeliverablePutOnHold::class,
            DeliverableResumed::class,
            WorkOrderCreated::class,
            DeliverableUrgent::class,
            DeliverableDueToday::class,
            DeliverableOverdue::class,
        ];
    }

    public function __construct(private LegacyProjection $projection) {}

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof DeliverableMoved => $this->moved($event),
            $event instanceof DeliverableBlocked => $this->blocked($event),
            $event instanceof BlockResolved => $this->send('order_unblocked', 'Unblocked', $event->deliverable, actor: $event->actor),
            $event instanceof DeliverablePutOnHold => $this->heldOrResumed($event->deliverable, $event->actor, toHold: true),
            $event instanceof DeliverableResumed => $this->heldOrResumed($event->deliverable, $event->actor, toHold: false),
            $event instanceof WorkOrderCreated => $this->created($event),
            $event instanceof DeliverableUrgent => $this->send('order_urgent', 'Urgent', $event->deliverable, actor: $event->actor, urgent: true),
            $event instanceof DeliverableDueToday => $this->send('order_due_today', 'Due Today', $event->deliverable),
            $event instanceof DeliverableOverdue => $this->send('order_overdue', 'Overdue', $event->deliverable, urgent: true),
            default => null,
        };
    }

    private function moved(DeliverableMoved $event): void
    {
        $order = $this->legacyOrder($event->deliverable);
        $queue = $order?->designer?->queue_status_value;
        $from = $this->projection->coreStatusFor($event->from, $queue);
        $to = $this->projection->coreStatusFor($event->to, $queue);

        if ($from !== $to) {
            $this->sendStatusChange($event->deliverable, $from, $to, $event->actor);
        }

        if (in_array($event->to, [DeliverableStatus::PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION], true)) {
            $this->send('order_approved_alta', 'Approved', $event->deliverable, __('Pending ALTA'), $event->actor, detailKey: 'Pending ALTA');
        }
    }

    private function heldOrResumed(Deliverable $deliverable, ?User $actor, bool $toHold): void
    {
        $queue = $this->legacyOrder($deliverable)?->designer?->queue_status_value;
        $active = $this->projection->coreStatusFor($deliverable->status, $queue);

        $this->sendStatusChange(
            $deliverable,
            $toHold ? $active : CoreStatus::ON_HOLD,
            $toHold ? CoreStatus::ON_HOLD : $active,
            $actor,
        );
    }

    private function sendStatusChange(Deliverable $deliverable, CoreStatus $from, CoreStatus $to, ?User $actor): void
    {
        $this->send(
            'status_changed',
            'Status',
            $deliverable,
            $from->label().' ➔ '.$to->label(),
            $actor,
            detailKey: ':from ➔ :to',
            detailParams: ['from' => ['core_status' => $from->value], 'to' => ['core_status' => $to->value]],
        );
    }

    private function blocked(DeliverableBlocked $event): void
    {
        $reason = $event->block->reason->label();

        $this->send('order_blocked', 'Blocked', $event->deliverable, $reason, $event->actor, urgent: true, detailKey: ':reason', detailParams: $this->blockParams($event, $reason));
        $this->notifyBlockOwner($event, $reason);
    }

    private function created(WorkOrderCreated $event): void
    {
        foreach (Deliverable::query()->whereKey($event->deliverableIds)->get() as $deliverable) {
            $this->send('new_order', 'New Order', $deliverable, actor: $event->actor);
        }
    }

    /**
     * Managers and lead designers already receive the block through the dispatcher. The owner may be neither.
     */
    private function notifyBlockOwner(DeliverableBlocked $event, string $reason): void
    {
        $owner = $event->block->owner;
        $order = $this->legacyOrder($event->deliverable);

        if ($owner === null || ! $owner->active || $order === null || $owner->getKey() === $event->actor?->getKey()) {
            return;
        }

        if (! $this->isEnabled('order_blocked')) {
            return;
        }

        $alreadyNotified = $owner->hasRole(UserRole::ADMIN, UserRole::COORDINATOR) || (bool) $owner->designer?->is_lead;
        if ($alreadyNotified) {
            return;
        }

        $owner->notify(new SystemNotification(
            eventType: 'order_blocked',
            label: 'Blocked',
            companyName: $order->company_name ?? __('General'),
            taskName: $order->task_name ?? __('Order'),
            orderId: $order->id,
            detailText: $reason,
            actorId: $event->actor?->getKey(),
            actorName: $event->actor?->name,
            isUrgent: true,
            designerId: $order->designer_id,
            designerName: $order->designer?->name,
            detailKey: ':reason',
            detailParams: $this->blockParams($event, $reason),
        ));
    }

    /**
     * The block reason code travels with the rendered text so the bell can show it in the reader's language.
     *
     * @return array{reason: string, block_reason: string}
     */
    private function blockParams(DeliverableBlocked $event, string $reason): array
    {
        return ['reason' => $reason, 'block_reason' => $event->block->reason->value];
    }

    /**
     * @param  array<string, mixed>  $detailParams
     */
    private function send(
        string $eventType,
        string $label,
        Deliverable $deliverable,
        ?string $detailText = null,
        ?User $actor = null,
        bool $urgent = false,
        ?string $detailKey = null,
        array $detailParams = [],
    ): void {
        $order = $this->legacyOrder($deliverable);
        if ($order === null) {
            return;
        }

        NotificationDispatcher::dispatch(
            eventType: $eventType,
            label: $label,
            order: $order,
            detailText: $detailText,
            actor: $actor,
            isUrgent: $urgent,
            detailKey: $detailKey,
            detailParams: $detailParams,
        );
    }

    /**
     * Admins can switch a notification type off in the settings; everything enabled by default.
     */
    private function isEnabled(string $eventType): bool
    {
        $settings = Setting::get('admin_notification_settings', []);
        $settings = is_array($settings) ? $settings : (json_decode((string) $settings, true) ?? []);

        return ($settings[$eventType] ?? true) !== false;
    }

    private function legacyOrder(Deliverable $deliverable): ?Order
    {
        return Order::query()->with('designer')->find($deliverable->getKey());
    }
}
