<?php

namespace App\Livewire\WorkOrders;

use App\Application\Delivery\Actions\AddDeliverable;
use App\Application\Delivery\Actions\AddPhase;
use App\Application\Delivery\Actions\ApproveAllInClientReview;
use App\Application\Delivery\Actions\LogReceipt;
use App\Application\Delivery\Actions\ResolveAlert;
use App\Application\Delivery\Actions\SendBatchToClient;
use App\Application\Delivery\Actions\UpdateWorkOrderDetails;
use App\Application\Delivery\Actions\UpdateWorkOrderOverviewFields;
use App\Application\Delivery\Data\WorkOrderPage;
use App\Application\Delivery\Queries\WorkOrderPageQuery;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\Roles;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Support\TitleCase;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * One work order: its client data, roll-up meters, alerts and one section per phase with its deliverables.
 */
class WorkOrderShow extends Component
{
    public WorkOrder $workOrder;

    public function mount(WorkOrder $workOrder): void
    {
        $this->workOrder = $workOrder;
    }

    #[Computed]
    public function page(): WorkOrderPage
    {
        return (new WorkOrderPageQuery)->run($this->workOrder);
    }

    /**
     * Managers edit the record data of the work order.
     */
    #[Computed]
    public function canEdit(): bool
    {
        return Roles::isManager(Auth::user());
    }

    /**
     * Sales cannot log receipts.
     */
    #[Computed]
    public function canLogReceipt(): bool
    {
        return Auth::user()?->role !== UserRole::SALES;
    }

    /**
     * Designers never see blocked deliverables.
     */
    #[Computed]
    public function hidesBlocked(): bool
    {
        $user = Auth::user();

        return $user?->role === UserRole::DESIGNER && ! Roles::isManager($user) && ! Roles::isReviewer($user);
    }

    #[Computed]
    public function waitingForClientCount(): int
    {
        return $this->countInStatuses([DeliverableStatus::CLIENT_REVIEW], false);
    }

    #[Computed]
    public function waitingForReceiptCount(): int
    {
        return $this->countInStatuses([DeliverableStatus::PRODUCED, DeliverableStatus::FULFILLMENT], true);
    }

    #[Computed]
    public function reviewerName(): string
    {
        $reviewer = User::query()->where('is_reviewer', true)->where('active', true)->orderBy('id')->first(['id', 'name']);

        return $reviewer !== null ? (explode(' ', trim($reviewer->name))[0] ?: __('Reviewer')) : __('Reviewer');
    }

    #[Computed]
    public function portalUrl(): ?string
    {
        return $this->page()->workOrder->client?->portal_url;
    }

    /**
     * Companies matching a search, for the client field of the edit form.
     *
     * @return list<array{id: int, name: string}>
     */
    #[Renderless]
    public function searchClients(string $term): array
    {
        abort_unless($this->canEdit(), 403);

        return Client::query()
            ->where('name', 'like', '%'.trim($term).'%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => TitleCase::from($client->name)])
            ->all();
    }

    /**
     * Locations and contacts of a company, for the edit form.
     *
     * @return array{locations: list<array{id: int, name: string}>, contacts: list<array{id: int, name: string}>}
     */
    #[Renderless]
    public function clientOptions(?int $clientId): array
    {
        abort_unless($this->canEdit(), 403);

        $client = $clientId !== null ? Client::query()->find($clientId) : null;

        return [
            'locations' => $client?->locations()->get(['id', 'name'])
                ->map(fn ($location): array => ['id' => $location->id, 'name' => TitleCase::from($location->name)])->all() ?? [],
            'contacts' => $client?->contacts()->get(['contacts.id', 'contacts.name'])
                ->map(fn ($contact): array => ['id' => $contact->id, 'name' => TitleCase::from($contact->name)])->all() ?? [],
        ];
    }

    /**
     * Saves the record data the manager already confirmed in the browser.
     *
     * @param  array<string, mixed>  $changes
     */
    public function saveDetails(array $changes): void
    {
        $this->attempt(function () use ($changes): void {
            $overview = array_intersect_key($changes, array_flip(UpdateWorkOrderOverviewFields::FIELDS));
            $linked = array_intersect_key($changes, array_flip(UpdateWorkOrderDetails::FIELDS));

            foreach (['client_id', 'client_location_id', 'contact_id'] as $key) {
                if (array_key_exists($key, $linked)) {
                    $linked[$key] = filled($linked[$key]) ? (int) $linked[$key] : null;
                }
            }
            if (array_key_exists('fulfillment_method', $linked)) {
                $linked['fulfillment_method'] = FulfillmentMethod::tryFrom((string) $linked['fulfillment_method']);
            }

            if ($overview !== []) {
                app(UpdateWorkOrderOverviewFields::class)($this->workOrder, $overview, Auth::user());
            }
            if ($linked !== []) {
                app(UpdateWorkOrderDetails::class)($this->workOrder, $linked, Auth::user());
            }

            $this->workOrder->refresh();
            $this->dispatch('toast', message: __('Work order updated.'));
        });
    }

    /**
     * Sends a phase (or the whole work order when it has no phases) to the client.
     */
    public function sendToClient(?int $phaseId = null): void
    {
        $this->attempt(function () use ($phaseId): void {
            $batch = $phaseId !== null ? Phase::query()->where('work_order_id', $this->workOrder->getKey())->findOrFail($phaseId) : $this->workOrder;
            $moved = app(SendBatchToClient::class)($batch, Auth::user());
            $this->dispatch('toast', message: trans_choice(':count deliverable sent to the client.|:count deliverables sent to the client.', $moved->count(), ['count' => $moved->count()]));
        });
    }

    public function approveAll(string $source, bool $measurementsConfirmed, bool $estimateApproved, string $note = ''): void
    {
        $this->attempt(function () use ($source, $measurementsConfirmed, $estimateApproved, $note): void {
            $approvalSource = ApprovalSource::tryFrom($source);
            if ($approvalSource === null || $approvalSource === ApprovalSource::REVIEWER) {
                throw WorkflowException::refused('Choose who approved the work.');
            }

            $approvals = app(ApproveAllInClientReview::class)(
                $this->workOrder,
                $approvalSource,
                $measurementsConfirmed,
                $estimateApproved,
                filled($note) ? trim($note) : null,
                null,
                Auth::user(),
            );
            $this->dispatch('toast', message: trans_choice(':count approval recorded.|:count approvals recorded.', $approvals->count(), ['count' => $approvals->count()]));
        });
    }

    public function confirmAllReceived(string $method, string $date, string $note = ''): void
    {
        $this->attempt(function () use ($method, $date, $note): void {
            $fulfillment = FulfillmentMethod::tryFrom($method);
            if ($fulfillment === null) {
                throw WorkflowException::refused('Choose how the client received the work.');
            }

            $receivedAt = filled($date) ? CarbonImmutable::parse($date) : null;
            $receipts = app(LogReceipt::class)($this->workOrder, $fulfillment, Auth::user(), $receivedAt, filled($note) ? trim($note) : null);
            $this->dispatch('toast', message: trans_choice(':count receipt logged.|:count receipts logged.', $receipts->count(), ['count' => $receipts->count()]));
        });
    }

    public function addDeliverable(string $name, string $size = '', string $material = '', ?int $quantity = null, ?int $phaseId = null): void
    {
        $this->attempt(function () use ($name, $size, $material, $quantity, $phaseId): void {
            if (trim($name) === '') {
                throw WorkflowException::refused('Give the deliverable a name.');
            }

            $phase = $phaseId ? Phase::query()->where('work_order_id', $this->workOrder->getKey())->findOrFail($phaseId) : null;
            app(AddDeliverable::class)(
                workOrder: $this->workOrder,
                name: $name,
                size: filled($size) ? trim($size) : null,
                material: filled($material) ? trim($material) : null,
                quantity: $quantity,
                phase: $phase,
                actor: Auth::user(),
            );
            $this->dispatch('toast', message: __('Deliverable added.'));
        });
    }

    public function addPhase(string $name, string $promisedDate = ''): void
    {
        $this->attempt(function () use ($name, $promisedDate): void {
            app(AddPhase::class)($this->workOrder, $name, filled($promisedDate) ? CarbonImmutable::parse($promisedDate) : null, Auth::user());
            $this->dispatch('toast', message: __('Phase added.'));
        });
    }

    public function logFollowUp(int $alertId): void
    {
        $alert = Alert::query()->findOrFail($alertId);
        abort_unless($this->page()->alerts->contains('id', $alert->id), 404);

        $this->attempt(function () use ($alert): void {
            app(ResolveAlert::class)($alert, Auth::user());
            $this->dispatch('toast', message: __('Follow-up logged.'));
        });
    }

    /**
     * @param  list<DeliverableStatus>  $statuses
     */
    private function countInStatuses(array $statuses, bool $skipStopped): int
    {
        return collect($this->page()->groups)
            ->flatMap(fn ($group) => $group->deliverables)
            ->filter(fn (Deliverable $deliverable): bool => in_array($deliverable->status, $statuses, true)
                && ! $deliverable->openHold
                && ! ($skipStopped && $deliverable->openBlock))
            ->count();
    }

    /**
     * Runs a use case and shows a refusal as a toast instead of an error page.
     */
    private function attempt(callable $useCase): void
    {
        try {
            $useCase();
        } catch (WorkflowException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        } catch (AuthorizationException) {
            $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');
        }

        unset($this->page, $this->waitingForClientCount, $this->waitingForReceiptCount, $this->portalUrl);
    }

    public function render()
    {
        $workOrder = $this->page()->workOrder;

        return view('livewire.work-orders.work-order-show')
            ->layout('components.layouts.app', ['title' => ($workOrder->number ?: __('Work order')).' - '.config('app.name')]);
    }
}
