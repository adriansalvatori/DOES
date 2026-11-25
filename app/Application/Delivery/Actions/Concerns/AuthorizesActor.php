<?php

namespace App\Application\Delivery\Actions\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

trait AuthorizesActor
{
    /**
     * Runs the policy ability for a person. Work started by the system (no actor) is trusted.
     */
    protected function authorizeActor(?User $actor, string $ability, mixed $subject): void
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize($ability, $subject);
        }
    }
}
