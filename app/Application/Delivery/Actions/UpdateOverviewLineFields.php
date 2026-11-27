<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edits the Excel-style notes, dates, checkmark and estimate review of an Overview line. The Overview shows one line
 * per work order, so by default the value is written to every deliverable of it; the accordion edits one deliverable.
 * Managers only.
 */
class UpdateOverviewLineFields
{
    use AuthorizesActor;

    public const FIELDS = ['production_note', 'delivery_note', 'email_date', 'production_processed_at', 'overview_checked', 'review_status'];

    /**
     * Who reviewed the estimate before it became an invoice.
     */
    public const REVIEW_STATUSES = ['CS', 'CAMILA'];

    private const DATE_FIELDS = ['email_date', 'production_processed_at'];

    public function __construct(private DeliveryLog $log) {}

    /**
     * @param  array{production_note?: ?string, delivery_note?: ?string, email_date?: ?string, production_processed_at?: ?string, overview_checked?: bool, review_status?: ?string}  $changes
     * @param  ?Deliverable  $only  Edit just this deliverable of the work order instead of all of them.
     * @return array<string, array{old: mixed, new: mixed}> The fields that actually changed.
     */
    public function __invoke(WorkOrder $workOrder, array $changes, ?User $actor = null, ?Deliverable $only = null): array
    {
        if ($actor !== null && ! Roles::isManager($actor)) {
            throw WorkflowException::managerOnly();
        }

        if ($only !== null && $only->work_order_id !== $workOrder->getKey()) {
            throw WorkflowException::refused('That deliverable belongs to another work order.');
        }

        return DB::transaction(function () use ($workOrder, $changes, $actor, $only): array {
            $deliverables = $workOrder->deliverables()->when($only !== null, fn ($query) => $query->whereKey($only->getKey()))->orderBy('id')->get();
            $diff = [];

            foreach (array_intersect_key($changes, array_flip(self::FIELDS)) as $field => $value) {
                $new = $this->normalize($field, $value);
                $current = $deliverables->map(fn ($deliverable): mixed => $this->stored($field, $deliverable->getAttribute($field)));
                $old = $field === 'overview_checked'
                    ? $deliverables->isNotEmpty() && $current->every(fn ($each): bool => $each === true)
                    : $current->first(fn ($each): bool => filled($each));

                if ($current->contains(fn ($each): bool => $each != $new)) {
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

        if ($field === 'review_status') {
            $text = strtoupper($text);

            if ($text !== '' && ! in_array($text, self::REVIEW_STATUSES, true)) {
                throw WorkflowException::refused('Choose CS, Camila or nobody.');
            }
        }

        return $text === '' ? null : $text;
    }

    /**
     * The stored value in the same shape normalize() produces, so they compare.
     */
    private function stored(string $field, mixed $value): mixed
    {
        if ($field === 'overview_checked') {
            return (bool) $value;
        }

        if (! filled($value)) {
            return null;
        }

        return in_array($field, self::DATE_FIELDS, true) ? substr((string) $value, 0, 10) : $value;
    }
}
