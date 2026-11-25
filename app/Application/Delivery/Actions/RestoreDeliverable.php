<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\AlertScheduler;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Brings a trashed deliverable back with the tasks that were trashed with it and the alerts of its stage.
 */
class RestoreDeliverable
{
    use AuthorizesActor;

    public function __construct(
        private AlertScheduler $alerts,
        private DeliveryLog $log,
        private LegacyProjection $projection,
    ) {}

    public function __invoke(Deliverable $deliverable, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'restore', $deliverable);

        return DB::transaction(function () use ($deliverable, $actor): Deliverable {
            $trashedAt = $deliverable->deleted_at ?? now();

            $deliverable->restore();
            Task::onlyTrashed()
                ->where('order_id', $deliverable->getKey())
                ->where('deleted_at', '>=', $trashedAt->copy()->subSeconds(5))
                ->restore();

            match ($deliverable->status) {
                DeliverableStatus::CLIENT_REVIEW => $this->alerts->scheduleClientFollowUps($deliverable, $deliverable->last_sent_to_client_at ?? now()),
                DeliverableStatus::INTERNAL_REVIEW => $this->alerts->scheduleReviewerFollowUp($deliverable, false),
                default => null,
            };

            $this->log->record($deliverable, 'deliverable_restored', $actor, null, $deliverable->status->value);
            $this->projection->project($deliverable);

            return $deliverable;
        });
    }
}
