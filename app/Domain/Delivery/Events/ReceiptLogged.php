<?php

namespace App\Domain\Delivery\Events;

use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReceiptLogged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Deliverable $deliverable,
        public Receipt $receipt,
        public ?User $actor = null,
    ) {}
}
