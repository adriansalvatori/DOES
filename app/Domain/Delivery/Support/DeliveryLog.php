<?php

namespace App\Domain\Delivery\Support;

use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\DeliverableEvent;
use App\Models\User;

/**
 * Writes the audit trail of a deliverable.
 */
class DeliveryLog
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Deliverable $deliverable,
        string $type,
        ?User $actor = null,
        ?string $previous = null,
        ?string $new = null,
        array $metadata = [],
    ): DeliverableEvent {
        return DeliverableEvent::create([
            'order_id' => $deliverable->getKey(),
            'event_type' => $type,
            'actor' => $actor?->name ?? 'system',
            'previous_value' => $previous,
            'new_value' => $new,
            'metadata' => $metadata + [
                'actor_id' => $actor?->getKey(),
                'actor_kind' => $actor ? 'user' : 'system',
            ],
        ]);
    }
}
