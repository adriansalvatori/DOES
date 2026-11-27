<?php

namespace App\Livewire\Overview\Page;

use App\Application\Delivery\Actions\AssignDeliverable;
use App\Application\Delivery\Actions\UpdateOverviewLineFields;
use App\Application\Delivery\Actions\UpdateWorkOrderOverviewFields;
use App\Application\Delivery\Data\OverviewFilters;
use App\Application\Delivery\Data\OverviewRow;
use App\Application\Delivery\Queries\OverviewQuery;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One Overview line: the Excel columns of a work order plus the accordion with its deliverables. Edits go through
 * the Actions and only this line re-renders.
 */
class OverviewRowLine extends Component
{
    public int $workOrderId;

    public string $tab = 'all';

    protected ?OverviewRow $preloaded = null;

    public function mount(int $workOrderId, string $tab = 'all', ?OverviewRow $row = null): void
    {
        $this->workOrderId = $workOrderId;
        $this->tab = $tab;
        $this->preloaded = $row;
    }

    /**
     * The line as the Overview query builds it. The parent hands it over on first render; later renders reload it.
     */
    public function row(): ?OverviewRow
    {
        if ($this->preloaded !== null) {
            return $this->preloaded;
        }

        $page = app(OverviewQuery::class)->run(new OverviewFilters(tab: $this->tab, workOrderId: $this->workOrderId), 1);

        return collect($page->items())->first() ?? $this->loadIgnoringTab();
    }

    /**
     * An edit can move a line out of its tab; keep showing it until the next page load.
     */
    private function loadIgnoringTab(): ?OverviewRow
    {
        $page = app(OverviewQuery::class)->run(new OverviewFilters(tab: 'all', workOrderId: $this->workOrderId), 1);

        return collect($page->items())->first();
    }

    /**
     * Saves a work order column. Returns whether it was kept and the stored value, so the cell can show what was kept.
     */
    public function saveWorkOrderField(string $field, mixed $value): array
    {
        $this->guard();

        $workOrder = WorkOrder::query()->findOrFail($this->workOrderId);

        return $this->attempt(function () use ($workOrder, $field, $value): array {
            $diff = app(UpdateWorkOrderOverviewFields::class)($workOrder, [$field => $value], Auth::user());
            $this->dispatch('toast', message: __('Saved.'));

            return ['ok' => true, 'value' => array_key_exists($field, $diff) ? $diff[$field]['new'] : $this->storedValue($workOrder->refresh(), $field)];
        });
    }

    /**
     * Saves a note, date, checkmark or estimate review of the line: on every deliverable, or just one from the accordion.
     */
    public function saveLineField(string $field, mixed $value, ?int $deliverableId = null): array
    {
        $this->guard();

        $workOrder = WorkOrder::query()->findOrFail($this->workOrderId);
        $only = $deliverableId !== null ? Deliverable::query()->findOrFail($deliverableId) : null;

        return $this->attempt(function () use ($workOrder, $field, $value, $only): array {
            $diff = app(UpdateOverviewLineFields::class)($workOrder, [$field => $value], Auth::user(), $only);
            $this->dispatch('toast', message: __('Saved.'));

            return ['ok' => true, 'value' => array_key_exists($field, $diff) ? $diff[$field]['new'] : $value];
        });
    }

    /**
     * Hands the open deliverables of the line (or one of them) to a designer, or clears the designer.
     */
    public function assignDesigner(?int $userId, ?int $deliverableId = null): array
    {
        $this->guard();

        $assignee = $userId !== null ? User::query()->designTeam()->findOrFail($userId) : null;
        $deliverables = Deliverable::query()
            ->where('work_order_id', $this->workOrderId)
            ->when($deliverableId !== null, fn ($query) => $query->whereKey($deliverableId))
            ->get()
            ->reject(fn (Deliverable $deliverable): bool => (bool) $deliverable->status?->isClosed());

        return $this->attempt(function () use ($deliverables, $assignee): array {
            $changed = $deliverables->filter(fn (Deliverable $deliverable): bool => app(AssignDeliverable::class)($deliverable, $assignee, Auth::user()))->count();
            $this->dispatch('toast', message: $changed > 0 ? __('Saved.') : __('No changes.'));

            return ['ok' => true];
        });
    }

    /**
     * Someone else changed this line: render it again.
     */
    #[On('overview-line-stale.{workOrderId}')]
    public function refreshLine(): void {}

    private function storedValue(WorkOrder $workOrder, string $field): mixed
    {
        return $field === 'promised_date' ? $workOrder->promised_date?->toDateString() : $workOrder->{$field};
    }

    private function guard(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->hasRole(UserRole::ADMIN, UserRole::COORDINATOR), 403);
    }

    /**
     * @return array{ok: bool, value?: mixed, message?: string}
     */
    private function attempt(callable $useCase): array
    {
        try {
            return $useCase();
        } catch (WorkflowException $exception) {
            $message = $exception->getMessage();
        } catch (AuthorizationException) {
            $message = __('You are not allowed to do this.');
        }

        $this->dispatch('toast', message: $message, type: 'error');

        return ['ok' => false, 'message' => $message];
    }

    public function render()
    {
        return view('livewire.overview-page.overview-row-line', ['row' => $this->row()]);
    }
}
