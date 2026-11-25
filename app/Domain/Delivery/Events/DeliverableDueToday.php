<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Models\Deliverable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliverableDueToday
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Deliverable $deliverable,
    ) {}
}
