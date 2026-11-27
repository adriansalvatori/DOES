<?php

namespace App\Domain\Delivery\Policies;

use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;

/**
 * One ability per delivery action. Active staff may work on deliverables; the abilities below narrow who may
 * approve, plan, close, reopen or delete.
 */
class DeliverablePolicy
{
    public function createWorkOrder(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function addDeliverable(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function duplicate(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function updateDetails(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function changeReviewer(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user);
    }

    public function changeDueTarget(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user) || Roles::isReviewer($user);
    }

    public function addComment(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function attachFile(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function move(User $user, Deliverable $deliverable): bool
    {
        return $this->canWork($user, $deliverable);
    }

    public function block(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function resolveBlock(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user) || Roles::isCustomerService($user) || $this->canWork($user, $deliverable);
    }

    public function putOnHold(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user) || Roles::isCustomerService($user);
    }

    public function resume(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user) || Roles::isCustomerService($user);
    }

    public function approveByReviewer(User $user, Deliverable $deliverable): bool
    {
        return Roles::isReviewer($user) || Roles::isManager($user);
    }

    public function requestChangesByReviewer(User $user, Deliverable $deliverable): bool
    {
        return Roles::isReviewer($user) || Roles::isManager($user);
    }

    public function sendBatchToClient(User $user, Deliverable $deliverable): bool
    {
        return Roles::isReviewer($user) || $this->canWork($user, $deliverable);
    }

    public function recordClientApproval(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function recordClientChanges(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function requestProductionSample(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function manageTasks(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user);
    }

    public function plan(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user) || $deliverable->assignee_id === $user->getKey();
    }

    public function unplan(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user);
    }

    public function moveInProduction(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user) && ! $user->isSales();
    }

    public function logReceipt(User $user, Deliverable $deliverable): bool
    {
        return $this->isStaff($user) && ! $user->isSales();
    }

    public function close(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user);
    }

    public function reopen(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user);
    }

    public function trash(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user);
    }

    public function restore(User $user, Deliverable $deliverable): bool
    {
        return Roles::isManager($user);
    }

    private function isStaff(User $user): bool
    {
        return (bool) $user->active;
    }

    private function canWork(User $user, Deliverable $deliverable): bool
    {
        if (! $this->isStaff($user)) {
            return false;
        }

        if (Roles::isManager($user) || Roles::isCustomerService($user) || Roles::isReviewer($user)) {
            return true;
        }

        return $deliverable->assignee_id === null || $deliverable->assignee_id === $user->getKey();
    }
}
