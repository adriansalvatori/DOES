<?php

namespace App\Livewire\Overview\Page;

use App\Application\Delivery\Actions\UpdateOverviewLineFields;
use App\Application\Delivery\Actions\UpdateWorkOrderOverviewFields;
use App\Application\Delivery\Data\OverviewFilters;
use App\Application\Delivery\Data\OverviewRow;
use App\Application\Delivery\Queries\OverviewQuery;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\WorkOrder;
use App\Enums\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
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
     * Saves a note, date or checkmark of the line.
     */
    public function saveLineField(string $field, mixed $value): array
    {
        $this->guard();

        $workOrder = WorkOrder::query()->findOrFail($this->workOrderId);

        return $this->attempt(function () use ($workOrder, $field, $value): array {
            $diff = app(UpdateOverviewLineFields::class)($workOrder, [$field => $value], Auth::user());
            $this->dispatch('toast', message: __('Saved.'));

            return ['ok' => true, 'value' => array_key_exists($field, $diff) ? $diff[$field]['new'] : $value];
        });
    }

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
     * @return array{ok: bool, value?: mixed}
     */
    private function attempt(callable $useCase): array
    {
        try {
            return $useCase();
        } catch (WorkflowException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        } catch (AuthorizationException) {
            $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');
        }

        return ['ok' => false];
    }

    public function render()
    {
        return view('livewire.overview-page.overview-row-line', ['row' => $this->row()]);
    }
}
