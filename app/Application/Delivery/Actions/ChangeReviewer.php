<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hands the internal review of a deliverable to another reviewer, or clears it. Managers only; the new reviewer
 * must be an active reviewer or manager.
 */
class ChangeReviewer
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log) {}

    /**
     * @return bool Whether the reviewer actually changed.
     */
    public function __invoke(Deliverable $deliverable, ?User $reviewer, ?User $actor = null): bool
    {
        $this->authorizeActor($actor, 'changeReviewer', $deliverable);

        if ($reviewer !== null && (! $reviewer->active || ! (Roles::isReviewer($reviewer) || Roles::isManager($reviewer)))) {
            throw WorkflowException::refused('Choose an active reviewer or manager.');
        }

        return DB::transaction(function () use ($deliverable, $reviewer, $actor): bool {
            $deliverable->refresh();
            $previousId = $deliverable->reviewer_id;
            $newId = $reviewer?->getKey();

            if ($previousId === $newId) {
                return false;
            }

            $previous = $previousId !== null ? User::query()->whereKey($previousId)->value('name') : null;
            $deliverable->forceFill(['reviewer_id' => $newId])->save();

            $this->log->record($deliverable, 'reviewer_changed', $actor, $previous, $reviewer?->name, [
                'from_id' => $previousId,
                'to_id' => $newId,
            ]);

            return true;
        });
    }
}
