<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Events\ReceiptLogged;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Receipt;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logs that the client received the work. One deliverable, or every produced one of a work order (blocked and
 * on-hold ones stay). Each receipt completes its deliverable.
 */
class LogReceipt
{
    use AuthorizesActor;

    public function __construct(private DeliverableWorkflow $workflow, private DeliveryLog $log) {}

    /**
     * @return Collection<int, Receipt>
     */
    public function __invoke(
        Deliverable|WorkOrder $target,
        FulfillmentMethod $method,
        ?User $loggedBy = null,
        ?CarbonInterface $receivedAt = null,
        ?string $note = null,
    ): Collection {
        $waiting = [DeliverableStatus::PRODUCED, DeliverableStatus::FULFILLMENT];

        if ($target instanceof Deliverable) {
            if (! in_array($target->refresh()->status, $waiting, true)) {
                throw WorkflowException::refused('Only produced work can be received.');
            }
            $deliverables = collect([$target]);
        } else {
            $deliverables = $target->deliverables()->inStatus($waiting)->get()
                ->reject(fn (Deliverable $each): bool => $each->isBlocked() || $each->isOnHold());
        }

        if ($deliverables->isEmpty()) {
            throw WorkflowException::refused('Nothing is waiting to be received.');
        }

        foreach ($deliverables as $each) {
            $this->authorizeActor($loggedBy, 'logReceipt', $each);
        }

        return DB::transaction(fn (): Collection => $deliverables->map(function (Deliverable $deliverable) use ($method, $loggedBy, $receivedAt, $note): Receipt {
            if ($deliverable->refresh()->status === DeliverableStatus::PRODUCED) {
                $this->workflow->transition($deliverable, DeliverableStatus::FULFILLMENT, TransitionReason::MANUAL, $loggedBy);
            }

            $receipt = Receipt::create([
                'deliverable_id' => $deliverable->getKey(),
                'method' => $method,
                'logged_by' => $loggedBy?->getKey(),
                'received_at' => $receivedAt ?? now(),
                'note' => $note,
            ]);

            $this->log->record($deliverable, 'receipt_logged', $loggedBy, null, $method->value, ['receipt_id' => $receipt->getKey(), 'note' => $note]);

            $this->workflow->transition($deliverable, DeliverableStatus::DONE, TransitionReason::MANUAL, $loggedBy, ['close_reason' => CloseReason::COMPLETED, 'receipt' => true]);

            DB::afterCommit(fn () => ReceiptLogged::dispatch($deliverable, $receipt, $loggedBy));

            return $receipt;
        })->values());
    }
}
