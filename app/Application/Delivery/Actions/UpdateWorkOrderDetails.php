<?php

namespace App\Application\Delivery\Actions;

use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\Roles;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edits the linked records of a work order (client, location, contact) and its fulfillment method, and records the
 * old and new value on every deliverable. Managers only. The WO number, estimate and promised date go through
 * UpdateWorkOrderOverviewFields.
 */
class UpdateWorkOrderDetails
{
    public const FIELDS = ['client_id', 'client_location_id', 'contact_id', 'fulfillment_method'];

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection) {}

    /**
     * @param  array{client_id?: ?int, client_location_id?: ?int, contact_id?: ?int, fulfillment_method?: FulfillmentMethod|string|null}  $changes
     * @return array<string, array{old: mixed, new: mixed}> The fields that actually changed.
     */
    public function __invoke(WorkOrder $workOrder, array $changes, ?User $actor = null): array
    {
        if ($actor !== null && ! Roles::isManager($actor)) {
            throw WorkflowException::managerOnly();
        }

        return DB::transaction(function () use ($workOrder, $changes, $actor): array {
            $workOrder->refresh();
            $changes = array_intersect_key($changes, array_flip(self::FIELDS));

            $clientId = array_key_exists('client_id', $changes) ? $changes['client_id'] : $workOrder->client_id;
            if (array_key_exists('client_id', $changes) && $clientId !== null && ! Client::query()->whereKey($clientId)->exists()) {
                throw WorkflowException::refused('That company does not exist.');
            }

            if (array_key_exists('client_id', $changes) && $clientId !== $workOrder->client_id && ! array_key_exists('client_location_id', $changes)) {
                $changes['client_location_id'] = null;
            }

            $locationId = $changes['client_location_id'] ?? null;
            if ($locationId !== null && ! ClientLocation::query()->whereKey($locationId)->where('client_id', $clientId)->exists()) {
                throw WorkflowException::refused('That location does not belong to the company.');
            }

            $contactId = $changes['contact_id'] ?? null;
            if ($contactId !== null && ! Contact::query()->whereKey($contactId)->exists()) {
                throw WorkflowException::refused('That contact does not exist.');
            }

            $diff = [];
            foreach ($changes as $field => $value) {
                if ($field === 'fulfillment_method') {
                    $value = $value instanceof FulfillmentMethod ? $value : (filled($value) ? FulfillmentMethod::from($value) : null);
                    $old = $workOrder->fulfillment_method?->value;
                    $new = $value?->value;
                } else {
                    $old = $workOrder->{$field};
                    $new = $value === null || $value === '' ? null : (int) $value;
                }

                if ($old !== $new) {
                    $diff[$field] = ['old' => $old, 'new' => $new];
                    $workOrder->{$field} = $value;
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
}
