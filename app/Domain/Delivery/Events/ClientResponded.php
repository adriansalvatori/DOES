<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientResponded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Deliverable $deliverable,
        public ?string $note = null,
        public ?User $actor = null,
    ) {}
}
