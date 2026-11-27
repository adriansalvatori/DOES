<?php

namespace App\Domain\Delivery\Policies;

use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;

/**
 * Plan week rules for tasks. Managers plan anyone's work. A designer plans their own row: moves and reorders their
 * own tasks between their own days and completes or reopens them, but cannot hand work to someone else.
 */
class TaskPolicy
{
    /**
     * @param  ?User  $target  The person the task is being planned for, when that changes.
     */
    public function plan(User $user, Task $task, ?User $target = null): bool
    {
        if (! $user->active) {
            return false;
        }

        if (Roles::isManager($user)) {
            return true;
        }

        $ownsTask = $task->effectiveAssigneeId() === $user->getKey();
        $staysWithThem = $target === null || $target->is($user);

        return $user->designs() && $ownsTask && $staysWithThem;
    }
}
