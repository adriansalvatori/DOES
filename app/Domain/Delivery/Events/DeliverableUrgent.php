<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliverableUrgent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Deliverable $deliverable,
        public ?User $actor = null,
    ) {}
}
