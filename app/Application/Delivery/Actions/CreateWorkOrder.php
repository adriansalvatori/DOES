<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Contracts\WorkOrderNumberGenerator;
use App\Domain\Delivery\Events\DeliverableUrgent;
use App\Domain\Delivery\Events\WorkOrderCreated;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\AlertScheduler;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a work order with its deliverables, the welcome email alert and the creation events.
 */
class CreateWorkOrder
{
    use AuthorizesActor;

    public function __construct(
        private AddDeliverable $addDeliverable,
        private AlertScheduler $alerts,
        private WorkOrderNumberGenerator $numbers,
    ) {}

    public function __invoke(CreateWorkOrderData $data, ?User $actor = null): WorkOrder
    {
        $this->authorizeActor($actor, 'createWorkOrder', Deliverable::class);

        if ($data->deliverables === []) {
            throw WorkflowException::refused('Add at least one deliverable.');
        }

        $workOrder = DB::transaction(function () use ($data, $actor): WorkOrder {
            $number = $this->resolveNumber($data);

            $workOrder = WorkOrder::create([
                'number' => $number,
                'client_id' => $data->clientId,
                'company_name' => $this->companyNameFor($data),
                'client_location_id' => $data->clientLocationId,
                'contact_id' => $data->contactId,
                'description' => $data->description,
                'estimate_invoice_number' => $data->estimateInvoiceNumber,
                'promised_date' => $data->promisedDate?->toDateString(),
                'fulfillment_method' => $data->fulfillmentMethod,
                'installation_types' => $data->installationTypes ?: null,
                'notes' => $data->notes,
                'in_backlog' => false,
            ]);

            $phase = $data->phaseName !== null && trim($data->phaseName) !== ''
                ? Phase::create(['work_order_id' => $workOrder->getKey(), 'name' => trim($data->phaseName), 'position' => 1, 'promised_date' => $data->promisedDate?->toDateString()])
                : null;

            $created = [];
            foreach ($data->deliverables as $item) {
                $created[] = ($this->addDeliverable)(
                    workOrder: $workOrder,
                    name: $item['name'],
                    size: $item['size'] ?? null,
                    material: $item['material'] ?? null,
                    quantity: $item['quantity'] ?? null,
                    phase: $phase,
                    flags: $data->flags,
                    assigneeId: $data->assigneeId,
                    trelloLink: $data->trelloLink,
                    actor: $actor,
                );
            }

            $entry = $data->createdAt;
            $backdated = $entry !== null && $entry->lt(now()->subDays(7));
            if (! $backdated) {
                $this->alerts->scheduleWelcomeEmail($workOrder, $data->assigneeId ? User::find($data->assigneeId) : null, $entry);
            }

            DB::afterCommit(function () use ($workOrder, $created, $actor): void {
                WorkOrderCreated::dispatch($workOrder, array_map(fn (Deliverable $each): int => $each->getKey(), $created), $actor);
                foreach ($created as $deliverable) {
                    if ($deliverable->isUrgent()) {
                        DeliverableUrgent::dispatch($deliverable, $actor);
                    }
                }
            });

            return $workOrder;
        });

        return $workOrder->load('deliverables');
    }

    private function companyNameFor(CreateWorkOrderData $data): ?string
    {
        $linked = $data->clientId !== null ? Client::query()->find($data->clientId)?->name : null;
        $typed = $data->companyName !== null ? mb_strtoupper(trim($data->companyName), 'UTF-8') : null;

        return $linked ?? ($typed === '' ? null : $typed);
    }

    private function resolveNumber(CreateWorkOrderData $data): ?string
    {
        $number = $data->number !== null ? trim($data->number) : null;

        if (($number === null || $number === '') && $data->generateNumber) {
            $context = ['client_id' => $data->clientId, 'company_name' => $data->clientId ? Client::find($data->clientId)?->name : null, 'task_name' => $data->deliverables[0]['name'] ?? null];
            $number = $this->numbers->generateNext($context);
        }

        if ($number === null || $number === '') {
            return null;
        }

        if (WorkOrder::withTrashed()->where('number', $number)->exists()) {
            throw WorkflowException::refused('Work order :number already exists.', ['number' => $number]);
        }

        return $number;
    }
}
