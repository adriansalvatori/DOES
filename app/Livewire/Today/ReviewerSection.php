<?php

namespace App\Livewire\Today;

use App\Application\Delivery\Actions\ApproveByReviewer;
use App\Application\Delivery\Actions\RequestChangesByReviewer;
use App\Application\Delivery\Actions\SendBatchToClient;
use App\Application\Delivery\Data\PhaseGroup;
use App\Application\Delivery\Queries\TodayQuery;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Camila's internal review: deliverables grouped by work order and phase, approve or send back each one,
 * then send the finished batch to the client.
 */
class ReviewerSection extends Component
{
    public ?int $changesFor = null;

    public string $changesNote = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_reviewer, 403);
    }

    /**
     * @return list<PhaseGroup>
     */
    #[Computed]
    public function groups(): array
    {
        return app(TodayQuery::class)->forReviewer(auth()->user());
    }

    public function approve(int $deliverableId): void
    {
        $deliverable = Deliverable::query()->findOrFail($deliverableId);

        $this->attempt(fn () => app(ApproveByReviewer::class)($deliverable, null, auth()->user()));
    }

    public function startRequestChanges(int $deliverableId): void
    {
        $this->changesFor = $deliverableId;
        $this->changesNote = '';
        $this->resetValidation();
    }

    public function cancelRequestChanges(): void
    {
        $this->changesFor = null;
        $this->changesNote = '';
    }

    public function requestChanges(): void
    {
        $this->validate(['changesNote' => ['required', 'string', 'max:2000']], attributes: ['changesNote' => __('note')]);
        $deliverable = Deliverable::query()->findOrFail($this->changesFor);

        $this->attempt(function () use ($deliverable): void {
            app(RequestChangesByReviewer::class)($deliverable, trim($this->changesNote), auth()->user());
            $this->cancelRequestChanges();
        });
    }

    public function sendBatch(int $workOrderId, ?int $phaseId = null): void
    {
        $batch = $phaseId !== null
            ? Phase::query()->where('work_order_id', $workOrderId)->findOrFail($phaseId)
            : WorkOrder::query()->findOrFail($workOrderId);

        $this->attempt(function () use ($batch): void {
            $moved = app(SendBatchToClient::class)($batch, auth()->user());
            $this->dispatch('toast', message: trans_choice('{1} :count deliverable sent to the client|[2,*] :count deliverables sent to the client', $moved->count()));
        });
    }

    private function attempt(callable $useCase): void
    {
        try {
            $useCase();
        } catch (WorkflowException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        } catch (AuthorizationException) {
            $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');
        }

        unset($this->groups);
    }

    /**
     * The panel changed a deliverable: reload this section only.
     */
    #[On('deliverable-changed')]
    public function refreshAfterPanelChange(): void
    {
        unset($this->groups);
    }

    public function render()
    {
        return view('livewire.today.reviewer-section');
    }
}
