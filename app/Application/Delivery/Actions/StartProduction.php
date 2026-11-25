<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;

/**
 * Production starts working on a deliverable from its queue.
 */
class StartProduction
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow) {}

    public function __invoke(Deliverable $deliverable, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'moveInProduction', $deliverable);

        return $this->workflow->transition($deliverable, DeliverableStatus::IN_PRODUCTION, TransitionReason::MANUAL, $actor);
    }
}
