<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edits the Excel-style notes, dates and checkmark of an Overview line. The Overview shows one line per work order,
 * so the value is written to every open deliverable of it. Managers only.
 */
class UpdateOverviewLineFields
{
    use AuthorizesActor;

    public const FIELDS = ['production_note', 'delivery_note', 'email_date', 'production_processed_at', 'overview_checked'];

    private const DATE_FIELDS = ['email_date', 'production_processed_at'];

    public function __construct(private DeliveryLog $log) {}

    /**
     * @param  array{production_note?: ?string, delivery_note?: ?string, email_date?: ?string, production_processed_at?: ?string, overview_checked?: bool}  $changes
     * @return array<string, array{old: mixed, new: mixed}> The fields that actually changed.
     */
    public function __invoke(WorkOrder $workOrder, array $changes, ?User $actor = null): array
    {
        if ($actor !== null && ! Roles::isManager($actor)) {
            throw WorkflowException::managerOnly();
        }

        return DB::transaction(function () use ($workOrder, $changes, $actor): array {
            $deliverables = $workOrder->deliverables()->orderBy('id')->get();
            $diff = [];

            foreach (array_intersect_key($changes, array_flip(self::FIELDS)) as $field => $value) {
                $new = $this->normalize($field, $value);
                $old = $deliverables->map(fn ($deliverable) => $deliverable->getAttribute($field))->first(fn ($each): bool => filled($each));
                $old = $field === 'overview_checked' ? $deliverables->isNotEmpty() && $deliverables->every(fn ($deliverable): bool => (bool) $deliverable->getAttribute($field)) : $old;
                $old = in_array($field, self::DATE_FIELDS, true) && $old !== null ? substr((string) $old, 0, 10) : $old;

                if ($old != $new) {
                    $diff[$field] = ['old' => $old, 'new' => $new];
                }
            }

            if ($diff === [] || $deliverables->isEmpty()) {
                return [];
            }

            $values = array_map(fn (array $change): mixed => $change['new'], $diff);
            DB::table('orders')->whereIn('id', $deliverables->modelKeys())->update($values + ['updated_at' => now()->toDateTimeString()]);

            foreach ($deliverables as $deliverable) {
                $this->log->record($deliverable, 'overview_updated', $actor, null, null, ['changes' => $diff]);
            }

            return $diff;
        });
    }

    private function normalize(string $field, mixed $value): mixed
    {
        if ($field === 'overview_checked') {
            return (bool) $value;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
