<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliverablePutOnHold
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Deliverable $deliverable,
        public Hold $hold,
        public ?User $actor = null,
    ) {}
}
