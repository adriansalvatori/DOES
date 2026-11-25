<?php

namespace App\Domain\Delivery\Support;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Finds who owns a block by default: the first active customer service user.
 * Null means the customer service queue, visible to admins and coordinators.
 */
class BlockOwnerResolver
{
    public function defaultOwner(): ?User
    {
        return User::query()
            ->withAnyRole(UserRole::CUSTOMER_SERVICE)
            ->where('active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * The reviewer who checks work before it reaches the client.
     */
    public function defaultReviewer(): ?User
    {
        return User::query()
            ->where('is_reviewer', true)
            ->where('active', true)
            ->orderBy('id')
            ->first();
    }
}
