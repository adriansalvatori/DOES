<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\DueDateHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Moves the due target by hand. Every change keeps the old and new date in the due date history, like the legacy
 * screens did, and in the audit trail.
 */
class ChangeDueTarget
{
    use AuthorizesActor;

    public const TRIGGER = 'manual_due_target';

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection) {}

    /**
     * @return bool Whether the date actually changed.
     */
    public function __invoke(Deliverable $deliverable, CarbonInterface $dueTarget, ?string $reason = null, ?User $actor = null): bool
    {
        $this->authorizeActor($actor, 'changeDueTarget', $deliverable);

        return DB::transaction(function () use ($deliverable, $dueTarget, $reason, $actor): bool {
            $deliverable->refresh();

            if ($deliverable->status->isClosed()) {
                throw WorkflowException::closed();
            }

            if ($deliverable->isOnHold()) {
                throw WorkflowException::refused('Work on hold has no due date. Resume it to set one.');
            }

            $previous = $deliverable->due_target;
            $new = $dueTarget->copy()->startOfDay();

            if ($previous !== null && $previous->isSameDay($new)) {
                return false;
            }

            $reason = filled($reason) ? trim((string) $reason) : null;

            DueDateHistory::query()->create([
                'order_id' => $deliverable->getKey(),
                'previous_due_date' => $previous?->toDateString(),
                'new_due_date' => $new->toDateString(),
                'reason' => $reason,
                'trigger_event' => self::TRIGGER,
                'created_by' => $actor?->name ?? 'system',
            ]);

            $deliverable->forceFill(['due_target' => $new->toDateString()])->save();

            $this->log->record($deliverable, 'due_target_changed', $actor, $previous?->toDateString(), $new->toDateString(), array_filter([
                'note' => $reason,
            ]));
            $this->projection->project($deliverable);

            return true;
        });
    }
}
