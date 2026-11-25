<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Scheduling\SlaPolicy;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a deliverable in intake with its creation rules: 3 business day due target, audit entry, legacy mirror.
 */
class AddDeliverable
{
    use AuthorizesActor;

    public function __construct(
        private SlaPolicy $sla,
        private DeliveryLog $log,
        private LegacyProjection $projection,
    ) {}

    /**
     * @param  list<DeliverableFlag>  $flags
     */
    public function __invoke(
        WorkOrder $workOrder,
        string $name,
        ?string $size = null,
        ?string $material = null,
        ?int $quantity = null,
        ?Phase $phase = null,
        array $flags = [],
        ?int $assigneeId = null,
        ?string $trelloLink = null,
        ?User $actor = null,
    ): Deliverable {
        $this->authorizeActor($actor, 'addDeliverable', Deliverable::class);

        return DB::transaction(function () use ($workOrder, $name, $size, $material, $quantity, $phase, $flags, $assigneeId, $trelloLink, $actor): Deliverable {
            $workOrder->loadMissing('client');

            $deliverable = Deliverable::create([
                'work_order_id' => $workOrder->getKey(),
                'phase_id' => $phase?->getKey(),
                'status' => DeliverableStatus::INTAKE,
                'company_name' => $workOrder->client?->name ?? __('No client'),
                'task_name' => trim($name),
                'size' => $size,
                'material' => $material,
                'quantity' => $quantity,
                'flag_codes' => $flags,
                'assignee_id' => $assigneeId,
                'trello_card_url' => $trelloLink,
                'due_target' => $this->sla->newWorkDueTarget()->toDateString(),
                'carry_over_count' => 0,
            ]);

            $this->log->record($deliverable, 'deliverable_created', $actor, null, $deliverable->task_name, [
                'work_order_id' => $workOrder->getKey(),
                'status' => DeliverableStatus::INTAKE->value,
            ]);

            $this->projection->project($deliverable);

            return $deliverable;
        });
    }
}
