<?php

namespace App\Domain\Delivery\Support;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;

/**
 * Production samples of a deliverable the client is reviewing. While any sample is still open the client is not
 * chased; when the last one ends (delivered, cancelled or trashed) the client follow-ups start again from that day.
 */
class ProductionSamples
{
    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const TRASHED = 'trashed';

    public function __construct(private AlertScheduler $alerts, private DeliveryLog $log) {}

    public function ended(Deliverable $sample, string $outcome, ?User $actor = null): void
    {
        if (! $sample->isSample()) {
            return;
        }

        $main = Deliverable::query()->find($sample->sample_of_id);

        if ($main === null || $main->status !== DeliverableStatus::CLIENT_REVIEW || $main->isOnHold()) {
            return;
        }

        if ($main->samples()->active()->whereKeyNot($sample->getKey())->exists()) {
            return;
        }

        $now = now();
        $main->forceFill(['last_sent_to_client_at' => $now])->save();
        $this->alerts->scheduleClientFollowUps($main, $now);
        $this->log->record($main, 'client_follow_ups_resumed', $actor, null, null, ['sample_id' => $sample->getKey(), 'sample_outcome' => $outcome]);
    }
}
