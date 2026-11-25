<?php

namespace App\Application\Delivery\Actions;

use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Adds a phase at the end of a work order. Managers and customer service only.
 */
class AddPhase
{
    public function __invoke(WorkOrder $workOrder, string $name, ?CarbonInterface $promisedDate = null, ?User $actor = null): Phase
    {
        if ($actor !== null && ! Roles::isManager($actor) && ! Roles::isCustomerService($actor)) {
            throw WorkflowException::managerOnly();
        }

        $name = trim($name);
        if ($name === '') {
            throw WorkflowException::refused('Give the phase a name.');
        }

        return Phase::create([
            'work_order_id' => $workOrder->getKey(),
            'name' => $name,
            'position' => ((int) $workOrder->phases()->max('position')) + 1,
            'promised_date' => $promisedDate?->toDateString() ?? $workOrder->promised_date?->toDateString(),
        ]);
    }
}
