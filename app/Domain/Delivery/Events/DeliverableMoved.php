<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliverableMoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Deliverable $deliverable,
        public DeliverableStatus $from,
        public DeliverableStatus $to,
        public TransitionReason $reason,
        public ?User $actor = null,
    ) {}
}
