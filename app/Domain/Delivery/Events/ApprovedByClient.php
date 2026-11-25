<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovedByClient
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Deliverable $deliverable,
        public Approval $approval,
        public ?User $actor = null,
    ) {}
}
