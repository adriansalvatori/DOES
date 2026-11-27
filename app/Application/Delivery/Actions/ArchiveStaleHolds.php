<?php

namespace App\Application\Delivery\Actions;

use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\LastActivity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Archives work that has been on hold with no activity for more than 30 days. It closes as "Archived: 30+ days on
 * hold", so it can be found and reopened. Backlog cards on hold are included.
 */
class ArchiveStaleHolds
{
    public const IDLE_DAYS = 30;

    public function __construct(private CloseDeliverable $close, private LastActivity $lastActivity) {}

    /**
     * @return int How many deliverables were archived.
     */
    public function __invoke(?CarbonInterface $now = null): int
    {
        $cutoff = CarbonImmutable::instance($now ?? now())->subDays(self::IDLE_DAYS);
        $held = Deliverable::query()->active()->onHold()->orderBy('id')->get();

        if ($held->isEmpty()) {
            return 0;
        }

        $activity = $this->lastActivity->forDeliverables($held->modelKeys());
        $archived = 0;

        foreach ($held as $deliverable) {
            $last = $activity[$deliverable->getKey()] ?? null;

            if ($last !== null && $last->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            ($this->close)($deliverable, CloseReason::INACTIVE_ON_HOLD);
            $archived++;
        }

        return $archived;
    }
}
