<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Merges work orders into one. The main work order keeps its identity and takes the field values chosen for it; the
 * deliverables, phases and alerts of the others move to it, and the others go to the trash marked as merged into it.
 */
class MergeWorkOrders
{
    use AuthorizesActor;

    /** Fields whose value can be taken from any of the merged work orders. */
    public const FIELDS = [
        'number', 'client_id', 'company_name', 'client_location_id', 'contact_id', 'description',
        'estimate_invoice_number', 'promised_date', 'fulfillment_method', 'installation_types', 'notes', 'drive_folder_id',
    ];

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection) {}

    /**
     * @param  list<WorkOrder>  $others
     * @param  array<string, mixed>  $fields  Chosen values by field (see FIELDS); missing fields keep the main value.
     */
    public function __invoke(WorkOrder $main, array $others, array $fields = [], ?User $actor = null): WorkOrder
    {
        $this->authorizeActor($actor, 'merge', Deliverable::class);

        $others = collect($others)->reject(fn (WorkOrder $workOrder): bool => $workOrder->is($main))->unique('id')->values();
        if ($others->isEmpty()) {
            throw WorkflowException::refused('Choose at least two work orders to merge.');
        }

        return DB::transaction(function () use ($main, $others, $fields, $actor): WorkOrder {
            $main->refresh();
            $otherIds = $others->map(fn ($other): int => (int) $other->getKey())->all();
            $before = $main->only(self::FIELDS);

            $main->fill(array_intersect_key($fields, array_flip(self::FIELDS)))->save();

            $nextPosition = (int) Phase::query()->where('work_order_id', $main->getKey())->max('position');
            foreach (Phase::query()->whereIn('work_order_id', $otherIds)->orderBy('work_order_id')->orderBy('position')->get() as $phase) {
                $phase->update(['work_order_id' => $main->getKey(), 'position' => ++$nextPosition]);
            }

            $hasWelcome = Alert::query()->open()->where('work_order_id', $main->getKey())->where('kind', AlertKind::WELCOME_EMAIL->value)->exists();
            Alert::query()->whereIn('work_order_id', $otherIds)
                ->when($hasWelcome, fn ($alerts) => $alerts->where('kind', '!=', AlertKind::WELCOME_EMAIL->value))
                ->update(['work_order_id' => $main->getKey()]);
            Alert::query()->open()->whereIn('work_order_id', $otherIds)->update(['resolved_at' => now()]);

            $moved = Deliverable::withTrashed()->whereIn('work_order_id', $otherIds)->get();
            Deliverable::withTrashed()->whereIn('work_order_id', $otherIds)->update(['work_order_id' => $main->getKey()]);

            foreach ($others as $other) {
                $other->forceFill(['merged_into_id' => $main->getKey()])->save();
                $other->delete();
            }

            $after = $main->only(self::FIELDS);
            $changed = [];
            foreach (self::FIELDS as $field) {
                if ($before[$field] != $after[$field]) {
                    $changed[$field] = ['old' => $before[$field], 'new' => $after[$field]];
                }
            }

            $summary = [
                'merged_work_orders' => $others->map(fn (WorkOrder $other): array => ['id' => $other->getKey(), 'number' => $other->number])->all(),
                'fields' => $changed,
            ];

            foreach ($moved as $deliverable) {
                $this->log->record($deliverable, 'work_order_merged', $actor, null, $main->number, $summary + ['into_work_order_id' => $main->getKey()]);
                $this->projection->project($deliverable);
            }
            foreach ($main->deliverables()->whereNotIn('id', $moved->modelKeys())->get() as $deliverable) {
                $this->log->record($deliverable, 'work_order_merged', $actor, null, $main->number, $summary + ['into_work_order_id' => $main->getKey()]);
                $this->projection->project($deliverable);
            }

            return $main->fresh();
        });
    }
}
