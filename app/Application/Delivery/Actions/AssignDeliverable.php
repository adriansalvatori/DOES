<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hands a deliverable to another designer, or clears the designer, without touching its planned day. Planning a day
 * goes through PlanDeliverable. Managers only.
 */
class AssignDeliverable
{
    use AuthorizesActor;

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection) {}

    /**
     * @return bool Whether the designer actually changed.
     */
    public function __invoke(Deliverable $deliverable, ?User $assignee, ?User $actor = null): bool
    {
        $this->authorizeActor($actor, 'assign', $deliverable);

        if ($assignee !== null && ! $assignee->active) {
            throw WorkflowException::refused('Choose an active designer.');
        }

        return DB::transaction(function () use ($deliverable, $assignee, $actor): bool {
            $deliverable->refresh();

            if ($deliverable->status->isClosed()) {
                throw WorkflowException::closed();
            }

            $previousId = $deliverable->assignee_id;
            $newId = $assignee?->getKey();

            if ($previousId === $newId) {
                return false;
            }

            $previous = $previousId !== null ? User::query()->whereKey($previousId)->value('name') : null;
            $deliverable->forceFill(['assignee_id' => $newId])->save();

            $this->log->record($deliverable, 'assignee_changed', $actor, $previous, $assignee?->name, [
                'from_id' => $previousId,
                'to_id' => $newId,
            ]);
            $this->projection->project($deliverable);

            return true;
        });
    }
}
