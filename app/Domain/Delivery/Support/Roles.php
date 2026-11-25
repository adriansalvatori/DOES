<?php

namespace App\Domain\Delivery\Support;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Who may do what in the delivery flow, in one place.
 */
final class Roles
{
    public static function isManager(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return in_array($user->role, [UserRole::ADMIN, UserRole::COORDINATOR], true) || (bool) $user->is_lead;
    }

    public static function isReviewer(?User $user): bool
    {
        return $user !== null && (bool) $user->is_reviewer;
    }

    public static function isCustomerService(?User $user): bool
    {
        return $user !== null && $user->role === UserRole::CUSTOMER_SERVICE;
    }
}
