<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\Roles;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edits the work order columns of the Overview screen (WO number, name, estimate or invoice, due date, installation) and
 * records the old and new value of each field on every deliverable of the work order. Managers only.
 */
class UpdateWorkOrderOverviewFields
{
    use AuthorizesActor;

    public const FIELDS = ['number', 'description', 'estimate_invoice_number', 'promised_date', 'installation_types'];

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection) {}

    /**
     * @param  array{number?: ?string, description?: ?string, estimate_invoice_number?: ?string, promised_date?: ?string, installation_types?: list<string>|null}  $changes
     * @return array<string, array{old: mixed, new: mixed}> The fields that actually changed.
     */
    public function __invoke(WorkOrder $workOrder, array $changes, ?User $actor = null): array
    {
        if ($actor !== null && ! Roles::isManager($actor)) {
            throw WorkflowException::managerOnly();
        }

        return DB::transaction(function () use ($workOrder, $changes, $actor): array {
            $workOrder->refresh();
            $diff = [];

            foreach (array_intersect_key($changes, array_flip(self::FIELDS)) as $field => $value) {
                $new = $this->normalize($field, $value);
                $old = $field === 'promised_date' ? $workOrder->promised_date?->toDateString() : $workOrder->{$field};

                if ($field === 'number' && $new === null) {
                    throw WorkflowException::refused('The WO number cannot be empty.');
                }
                if ($field === 'number' && $new !== $old && WorkOrder::query()->where('number', $new)->whereKeyNot($workOrder->getKey())->exists()) {
                    throw WorkflowException::refused('Another work order already uses this WO number.');
                }

                if ($old != $new) {
                    $diff[$field] = ['old' => $old, 'new' => $new];
                    $workOrder->{$field} = $new;
                }
            }

            if ($diff === []) {
                return [];
            }

            $workOrder->save();

            foreach ($workOrder->deliverables()->get() as $deliverable) {
                $this->log->record($deliverable, 'work_order_updated', $actor, null, null, ['changes' => $diff]);
                $this->projection->project($deliverable);
            }

            return $diff;
        });
    }

    private function normalize(string $field, mixed $value): mixed
    {
        if ($field === 'installation_types') {
            $types = array_values(array_unique(array_filter((array) $value, fn ($type): bool => filled($type))));

            return $types === [] ? null : $types;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if ($field === 'number' && ! str_starts_with(strtoupper($text), 'WO')) {
            return 'WO '.$text;
        }

        return $text;
    }
}
