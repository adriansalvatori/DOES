<?php

namespace App\Domain\Scheduling;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Work in progress limit per person. Blocked deliverables do not count.
 */
class WipPolicy
{
    public const DEFAULT_LIMIT = 4;

    public function limitFor(User $user): int
    {
        return (int) ($user->wip_limit ?? self::DEFAULT_LIMIT);
    }

    /**
     * Deliverables the person is designing right now.
     */
    public function inDesignCount(User $user): int
    {
        return Deliverable::query()
            ->inWorkspace()
            ->assignedTo($user)
            ->inStatus(DeliverableStatus::IN_DESIGN)
            ->notBlocked()
            ->count();
    }

    /**
     * Deliverables of the person planned for a day, not counting the one being planned.
     */
    public function plannedCount(User $user, CarbonInterface $day, ?Deliverable $except = null): int
    {
        return Deliverable::query()
            ->inWorkspace()
            ->assignedTo($user)
            ->inStatus([DeliverableStatus::READY, DeliverableStatus::IN_DESIGN])
            ->plannedFor($day)
            ->notBlocked()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->count();
    }

    public function hasRoomToPlan(User $user, CarbonInterface $day, ?Deliverable $except = null): bool
    {
        return $this->plannedCount($user, $day, $except) < $this->limitFor($user);
    }

    public function hasRoomToStart(User $user, int $startedMeanwhile = 0): bool
    {
        return $this->inDesignCount($user) + $startedMeanwhile < $this->limitFor($user);
    }
}
