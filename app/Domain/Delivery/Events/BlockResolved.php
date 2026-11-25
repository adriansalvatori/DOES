<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BlockResolved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Deliverable $deliverable,
        public Block $block,
        public ?User $actor = null,
    ) {}
}
