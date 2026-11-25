<?php

namespace App\Livewire\Backlog;

use App\Application\Delivery\Actions\MoveWorkOrderOutOfBacklog;
use App\Application\Delivery\Data\BacklogFilters;
use App\Application\Delivery\Data\BacklogResult;
use App\Application\Delivery\Queries\BacklogQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\WorkOrder;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Imported cards still waiting in the backlog. Moving one to Intake hands it to the daily screens.
 */
class BacklogPage extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'designer')]
    public ?int $assigneeId = null;

    #[Url(as: 'company')]
    public string $company = '';

    #[Url(as: 'contact')]
    public ?int $contactId = null;

    #[Url(as: 'sort')]
    public string $sortBy = 'created_desc';

    /**
     * @var list<int>
     */
    public array $selected = [];

    public function updating(string $property): void
    {
        if (! str_starts_with($property, 'selected')) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    #[Computed]
    public function result(): BacklogResult
    {
        return app(BacklogQuery::class)->run(new BacklogFilters(
            search: filled($this->search) ? $this->search : null,
            status: DeliverableStatus::tryFrom($this->status),
            assigneeId: $this->assigneeId,
            company: filled($this->company) ? $this->company : null,
            contactId: $this->contactId,
            sortBy: in_array($this->sortBy, ['created_desc', 'created_asc', 'due_date_asc', 'company_asc'], true) ? $this->sortBy : 'created_desc',
        ), self::PER_PAGE);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function designerOptions(): array
    {
        return User::query()->where('active', true)->where('role', UserRole::DESIGNER->value)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function moveToIntake(int $workOrderId): void
    {
        $this->move([$workOrderId]);
    }

    public function moveSelectedToIntake(): void
    {
        $this->move(array_map('intval', $this->selected));
        $this->selected = [];
    }

    public function selectPage(): void
    {
        $this->selected = collect($this->result->workOrders->items())->map(fn (WorkOrder $workOrder): int => $workOrder->id)->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'assigneeId', 'company', 'contactId');
        $this->resetPage();
        $this->selected = [];
    }

    /**
     * @param  list<int>  $workOrderIds
     */
    private function move(array $workOrderIds): void
    {
        $moved = 0;

        foreach (WorkOrder::query()->whereIn('id', $workOrderIds)->get() as $workOrder) {
            try {
                app(MoveWorkOrderOutOfBacklog::class)($workOrder, Auth::user());
                $moved++;
            } catch (WorkflowException $exception) {
                $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
            } catch (AuthorizationException) {
                $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');
            }
        }

        if ($moved > 0) {
            $this->dispatch('toast', message: trans_choice('{1} :count work order moved to Intake.|[2,*] :count work orders moved to Intake.', $moved, ['count' => $moved]));
        }

        unset($this->result);
    }

    public function render()
    {
        return view('livewire.backlog-page.backlog-page')
            ->layout('components.layouts.app', ['title' => __('Backlog').' - '.config('app.name')]);
    }
}
