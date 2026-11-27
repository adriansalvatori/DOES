<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\AlertScheduler;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Scheduling\SlaPolicy;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The client wants to see a printed sample before approving. A child deliverable is created in Production files and
 * goes through production and fulfillment on its own, while the main deliverable stays in Client review. Several
 * samples can be out at once. The client follow-ups of the main deliverable pause until the last open sample ends
 * (delivered, cancelled or trashed), then start again from that day.
 */
class RequestProductionSample
{
    use AuthorizesActor;

    public function __construct(
        private DeliverableWorkflow $workflow,
        private SlaPolicy $sla,
        private AlertScheduler $alerts,
        private DeliveryLog $log,
        private LegacyProjection $projection,
    ) {}

    public function __invoke(Deliverable $deliverable, ?string $note = null, ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'requestProductionSample', $deliverable);

        return DB::transaction(function () use ($deliverable, $note, $actor): Deliverable {
            $deliverable->refresh();

            if ($deliverable->status !== DeliverableStatus::CLIENT_REVIEW) {
                throw WorkflowException::refused('A production sample can only be requested while the client is reviewing the work.');
            }

            $sample = Deliverable::create([
                'work_order_id' => $deliverable->work_order_id,
                'phase_id' => $deliverable->phase_id,
                'sample_of_id' => $deliverable->getKey(),
                'status' => DeliverableStatus::PRODUCTION_FILES,
                'company_name' => $deliverable->company_name,
                'task_name' => $deliverable->task_name,
                'size' => $deliverable->size,
                'material' => $deliverable->material,
                'quantity' => $deliverable->quantity,
                'files_url' => $deliverable->files_url,
                'assignee_id' => $deliverable->assignee_id,
                'reviewer_id' => $deliverable->reviewer_id,
                'priority' => $deliverable->priority,
                'flag_codes' => $deliverable->flag_codes,
                'due_target' => $this->sla->approvalDueTarget($deliverable->isUrgent(), now())->toDateString(),
                'in_backlog' => false,
            ]);

            $this->workflow->ensureProductionFilesGate($sample);
            $this->alerts->resolve($deliverable, [AlertKind::CLIENT_FOLLOW_UP_1, AlertKind::CLIENT_FOLLOW_UP_2, AlertKind::CLIENT_FOLLOW_UP_3]);

            $this->log->record($sample, 'deliverable_created', $actor, null, DeliverableStatus::PRODUCTION_FILES->value, ['sample_of' => $deliverable->getKey(), 'note' => $note]);
            $this->log->record($deliverable, 'production_sample_requested', $actor, null, null, ['sample_id' => $sample->getKey(), 'note' => $note]);
            $this->projection->project($sample);

            return $sample;
        });
    }
}
