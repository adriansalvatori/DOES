<?php

namespace App\Application\Delivery\Data;

use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Models\User;

final readonly class ResolverItem
{
    public function __construct(
        public string $type,
        public Deliverable $deliverable,
        public Block|Hold $record,
        public BlockReason|HoldReason $reason,
        public ?string $note,
        public int $ageInDays,
        public ?User $owner,
        public ?string $clientPhone,
    ) {}
}
