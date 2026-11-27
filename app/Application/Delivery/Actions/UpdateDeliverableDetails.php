<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\Priority;
use App\Domain\Delivery\Events\DeliverableUrgent;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\PointsEstimator;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edits the record data of a deliverable and writes the old and new value of each field to its history.
 */
class UpdateDeliverableDetails
{
    use AuthorizesActor;

    public const FIELDS = ['name', 'size', 'material', 'quantity', 'files_url', 'trello_card_url', 'phase_id', 'flags', 'priority', 'points'];

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection) {}

    /**
     * @param  array{name?: string, size?: string|null, material?: string|null, quantity?: int|null, files_url?: string|null, trello_card_url?: string|null, phase_id?: int|null, flags?: list<DeliverableFlag>, priority?: Priority, points?: int|null}  $changes
     * @return array<string, array{old: mixed, new: mixed}> The fields that actually changed.
     */
    public function __invoke(Deliverable $deliverable, array $changes, ?User $actor = null): array
    {
        $this->authorizeActor($actor, 'updateDetails', $deliverable);

        $diff = [];
        $becameUrgent = false;

        DB::transaction(function () use ($deliverable, $changes, $actor, &$diff, &$becameUrgent): void {
            $deliverable->refresh();
            $wasUrgent = $deliverable->isUrgent();

            foreach (array_intersect_key($changes, array_flip(self::FIELDS)) as $field => $value) {
                if ($field === 'flags') {
                    $old = $deliverable->flag_codes?->map(fn (DeliverableFlag $flag): string => $flag->value)->values()->all() ?? [];
                    $new = array_map(fn (DeliverableFlag $flag): string => $flag->value, $value);
                    if ($old !== $new) {
                        $diff['flags'] = ['old' => $old, 'new' => $new];
                        $deliverable->flag_codes = $value;
                    }

                    continue;
                }

                if ($field === 'priority') {
                    $old = $deliverable->priority?->value;
                    if ($old !== $value->value) {
                        $diff['priority'] = ['old' => $old, 'new' => $value->value];
                        $deliverable->priority = $value;
                    }

                    continue;
                }

                if ($field === 'points') {
                    if ($deliverable->tasks()->exists()) {
                        throw WorkflowException::refused('Its points are the sum of its tasks. Change the points of a task instead.');
                    }
                    if ($value !== null && ! in_array($value, PointsEstimator::SCALE, true)) {
                        throw WorkflowException::refused('Points must be a Fibonacci number: 1, 2, 3, 5, 8 or 13.');
                    }
                }

                $attribute = $field === 'name' ? 'task_name' : $field;
                $old = $deliverable->{$attribute};
                $new = $field === 'name' ? trim((string) $value) : $value;
                if ($old !== $new && (string) $old !== (string) $new) {
                    $diff[$field] = ['old' => $old, 'new' => $new];
                    $deliverable->{$attribute} = $new;
                }
            }

            if ($diff === []) {
                return;
            }

            $deliverable->save();
            $becameUrgent = ! $wasUrgent && $deliverable->isUrgent();

            $this->log->record($deliverable, 'details_updated', $actor, null, null, ['changes' => $diff]);
            $this->projection->project($deliverable);

            if ($becameUrgent) {
                DB::afterCommit(fn () => DeliverableUrgent::dispatch($deliverable, $actor));
            }
        });

        return $diff;
    }
}
