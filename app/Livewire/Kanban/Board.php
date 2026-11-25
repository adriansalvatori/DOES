<?php

namespace App\Livewire\Kanban;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Livewire\Kanban\Concerns\BuildsKanbanOrdersQuery;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderElement;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Models\Substatus as SubstatusModel;
use App\Services\AutomationEngine;
use App\Services\OrderTitleParserService;
use App\Services\TrelloSyncService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class Board extends Component
{
    use BuildsKanbanOrdersQuery;

    public $search = '';

    public $designerFilter = 'all';

    public $substatusFilter = 'all';

    public $companyFilter = 'all';

    public $responsibleFilter = 'all';

    public $columnGroup = 'all'; // all, incoming, in_progress, final

    public bool $showOnHoldModal = false;

    public ?int $pendingOnHoldOrderId = null;

    public string $onHoldReason = '';

    public bool $showResumeModal = false;

    public ?int $pendingResumeOrderId = null;

    public ?string $pendingResumeNewStatus = null;

    public string $resumeReason = '';

    public bool $showBlockModal = false;

    public ?int $pendingBlockOrderId = null;

    public string $blockReason = 'FALTAN MEDIDAS';

    public string $blockReasonOther = '';

    public string $blockComment = '';

    public bool $requireCustomerService = false;

    public bool $showUnblockModal = false;

    public ?int $pendingUnblockOrderId = null;

    public string $unblockReason = '';

    public bool $showArchiveModal = false;

    public ?int $pendingArchiveOrderId = null;

    public string $archiveSubstatus = 'FINALIZADA !';

    public bool $showStandaloneTaskCards = false;

    /**
     * Last fingerprint of each column's cards, keyed by CoreStatus value.
     *
     * @var array<string, string>
     */
    public array $columnFingerprints = [];

    public function mount(): void
    {
        $user = Auth::user();
        if ($user && $user->isDesigner() && $user->designer) {
            $this->designerFilter = (string) $user->designer->id;
        }

        $this->columnFingerprints = $this->computeColumnFingerprints();
    }

    /**
     * Ask only the columns whose cards changed to re-render. Answers with no HTML
     * while nothing changed, so it is cheap to poll and to run after any event.
     */
    #[Renderless]
    #[On('order-updated')]
    #[On('task-added')]
    public function syncChangedColumns(): void
    {
        $current = $this->computeColumnFingerprints();

        foreach ($current as $columnValue => $fingerprint) {
            if (($this->columnFingerprints[$columnValue] ?? null) !== $fingerprint) {
                $this->dispatch('kanban-column-refresh.'.$columnValue);
            }
        }

        $this->columnFingerprints = $current;
    }

    /**
     * Filters live here but each column renders itself, so hand them over on change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'designerFilter', 'substatusFilter', 'companyFilter', 'responsibleFilter', 'showStandaloneTaskCards'], true)) {
            $this->dispatch('kanban-filters-changed', filters: [
                'search' => (string) $this->search,
                'designerFilter' => (string) $this->designerFilter,
                'substatusFilter' => (string) $this->substatusFilter,
                'companyFilter' => (string) $this->companyFilter,
                'responsibleFilter' => (string) $this->responsibleFilter,
                'showStandaloneTaskCards' => $this->showStandaloneTaskCards,
            ]);
            $this->columnFingerprints = $this->computeColumnFingerprints();
        }
    }

    /**
     * @return array<int, CoreStatus>
     */
    public function allColumns(): array
    {
        return array_merge(
            [CoreStatus::ENTRANTE],
            CoreStatus::designerQueueStatuses(),
            [
                CoreStatus::TO_DO_TODAY,
                CoreStatus::ENVIADO_A_CAMILA,
                CoreStatus::ENVIADO_AL_CLIENTE,
                CoreStatus::ON_HOLD,
                CoreStatus::EN_PRODUCCION,
                CoreStatus::ARCHIVED,
            ]
        );
    }

    /**
     * One hash per column covering its orders, their tasks and their elements.
     *
     * @return array<string, string>
     */
    protected function computeColumnFingerprints(): array
    {
        $orderRows = $this->ordersQuery(withRelations: false)
            ->reorder()
            ->select(['id', 'core_status', 'updated_at'])
            ->get();

        $stamps = fn (string $model) => $model::query()
            ->selectRaw('order_id, count(*) as total, max(updated_at) as stamp')
            ->groupBy('order_id')
            ->get()
            ->keyBy('order_id');

        $taskStamps = $stamps(RelatedTask::class);
        $elementStamps = $stamps(OrderElement::class);

        $parts = [];
        foreach ($orderRows as $row) {
            $key = $row->core_status instanceof CoreStatus ? $row->core_status->value : (string) $row->core_status;
            $task = $taskStamps->get($row->id);
            $element = $elementStamps->get($row->id);
            $parts[$key][] = $row->id.':'.$row->updated_at?->timestamp.':'.$task?->total.'/'.$task?->stamp.':'.$element?->total.'/'.$element?->stamp;
        }

        $allTasks = $taskStamps->count().'/'.$taskStamps->max('stamp');
        $fingerprints = [];
        foreach ($this->allColumns() as $column) {
            $extra = in_array($column, [CoreStatus::ENTRANTE, CoreStatus::TO_DO_TODAY], true) ? $allTasks : '';
            $fingerprints[$column->value] = md5($extra.'|'.implode('|', $parts[$column->value] ?? []));
        }

        return $fingerprints;
    }

    public function toggleStandaloneTaskCards(): void
    {
        $this->showStandaloneTaskCards = ! $this->showStandaloneTaskCards;
    }

    public function getSearchResultsProperty()
    {
        if (strlen(trim($this->search)) < 2) {
            return collect();
        }

        return Order::inWorkspace()
            ->with('designer')
            ->search($this->search)
            ->take(8)
            ->get();
    }

    public function selectSearchResult($orderId)
    {
        $this->dispatch('open-order-detail', orderId: $orderId);
    }

    public function moveOrder($orderId, $newStatusValue)
    {
        $order = Order::findOrFail($orderId);
        $previousStatus = $order->core_status;
        $newStatus = CoreStatus::from($newStatusValue);

        if ($newStatus === CoreStatus::ARCHIVED) {
            $this->pendingArchiveOrderId = $orderId;
            $this->archiveSubstatus = SubstatusModel::getDefaultArchivedSubstatus();
            $this->showArchiveModal = true;

            return;
        }

        if ($newStatus === CoreStatus::ON_HOLD) {
            $this->pendingOnHoldOrderId = $orderId;
            $this->onHoldReason = '';
            $this->showOnHoldModal = true;

            return;
        }

        if ($previousStatus === CoreStatus::ON_HOLD && $newStatus !== CoreStatus::ON_HOLD && $newStatus !== CoreStatus::ARCHIVED) {
            $this->pendingResumeOrderId = $orderId;
            $this->pendingResumeNewStatus = $newStatusValue;
            $this->resumeReason = '';
            $this->showResumeModal = true;

            return;
        }

        if ($newStatus === CoreStatus::ENTRANTE) {
            $this->pendingBlockOrderId = $orderId;
            $this->blockReason = 'FALTAN MEDIDAS';
            $this->blockReasonOther = '';
            $this->blockComment = '';
            $this->requireCustomerService = false;
            $this->showBlockModal = true;

            return;
        }

        if ($newStatus === CoreStatus::EN_PRODUCCION && ! $order->approved) {
            $this->dispatch('open-order-detail', orderId: $orderId, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);
            $this->dispatch('toast', message: __('The order requires approval before being sent to Production.'), type: 'info');

            return;
        }

        // Update local state instantly
        if ($newStatus === CoreStatus::EN_PRODUCCION) {
            $order->update([
                'core_status' => $newStatus,
                'substatus' => Substatus::ENVIADO_EN_ALTA,
                'done_today' => true,
            ]);
        } elseif (in_array($newStatus, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE], true)) {
            $order->update([
                'core_status' => $newStatus,
                'done_today' => true,
            ]);
        } else {
            $order->update(['core_status' => $newStatus]);
        }

        // Run local workflow automations
        app(AutomationEngine::class)->handleStatusChanged($order, $previousStatus, $newStatus, auth()->user()?->name ?? __('User'));

        // Optionally attempt Trello sync in background without interrupting UI
        $freshOrder = $order->fresh();
        if ($freshOrder && $freshOrder->trello_card_id) {
            try {
                $pushedTitle = OrderTitleParserService::buildTitle($freshOrder);
                $pushed = app(TrelloSyncService::class)->updateCardOnTrello($freshOrder);
                if ($pushed) {
                    $freshOrder->update(['trello_title' => $pushedTitle]);
                }
            } catch (\Throwable $e) {
                // Ignore remote network error so local drag-and-drop state is preserved
            }
        }

        $this->syncChangedColumns();
        session()->flash('message', __('Order :name moved to :status.', ['name' => $order->company_name, 'status' => $newStatus->label()]));
    }

    public function confirmBlock()
    {
        if (! $this->pendingBlockOrderId) {
            return;
        }

        $order = Order::findOrFail($this->pendingBlockOrderId);

        $order->block(
            reason: $this->blockReason,
            reasonOther: $this->blockReasonOther,
            comment: $this->blockComment,
            requireCS: $this->requireCustomerService,
            actor: 'Usuario'
        );

        $this->showBlockModal = false;
        $this->pendingBlockOrderId = null;
        $this->blockReason = 'FALTAN MEDIDAS';
        $this->blockReasonOther = '';
        $this->blockComment = '';
        $this->requireCustomerService = false;

        $this->syncChangedColumns();
        $this->dispatch('order-updated');
        session()->flash('message', __('Order :name marked as Blocked.', ['name' => $order->company_name]));
    }

    public function cancelBlock()
    {
        $this->showBlockModal = false;
        $this->pendingBlockOrderId = null;
        $this->blockReason = 'FALTAN MEDIDAS';
        $this->blockReasonOther = '';
        $this->blockComment = '';
        $this->requireCustomerService = false;
    }

    public function closeArchiveModal(): void
    {
        $this->showArchiveModal = false;
        $this->pendingArchiveOrderId = null;
        $this->archiveSubstatus = SubstatusModel::getDefaultArchivedSubstatus();
    }

    public function confirmArchive(): void
    {
        if (! $this->pendingArchiveOrderId) {
            return;
        }

        $order = Order::findOrFail($this->pendingArchiveOrderId);
        $previousStatus = $order->core_status;
        $subEnum = Substatus::tryFrom($this->archiveSubstatus) ?? $this->archiveSubstatus;

        $order->update([
            'core_status' => CoreStatus::ARCHIVED,
            'substatus' => $subEnum,
            'archived_at' => now(),
        ]);

        app(AutomationEngine::class)->handleStatusChanged($order, $previousStatus, CoreStatus::ARCHIVED, auth()->user()?->name ?? __('User'));

        $this->showArchiveModal = false;
        $this->pendingArchiveOrderId = null;
        $this->syncChangedColumns();
        $this->dispatch('order-updated');
        $this->dispatch('toast', message: __('Order archived.'));
    }

    public function openUnblockModal($orderId)
    {
        $this->pendingUnblockOrderId = $orderId;
        $this->unblockReason = '';
        $this->showUnblockModal = true;
    }

    public function confirmUnblock()
    {
        if (! $this->pendingUnblockOrderId) {
            return;
        }

        $this->validate([
            'unblockReason' => 'required|string|min:3',
        ], [
            'unblockReason.required' => __('Enter the reason or way the block was resolved.'),
            'unblockReason.min' => __('The reason must be at least 3 characters.'),
        ]);

        $order = Order::findOrFail($this->pendingUnblockOrderId);
        $order->unblock($this->unblockReason);

        $this->showUnblockModal = false;
        $this->pendingUnblockOrderId = null;
        $this->unblockReason = '';

        $this->syncChangedColumns();
        $this->dispatch('order-updated');
        session()->flash('message', __('Order :name unblocked and returned to the designer\'s list.', ['name' => $order->company_name]));
    }

    public function cancelUnblock()
    {
        $this->showUnblockModal = false;
        $this->pendingUnblockOrderId = null;
        $this->unblockReason = '';
    }

    public function confirmOnHold()
    {
        if (! $this->pendingOnHoldOrderId) {
            return;
        }

        $this->validate([
            'onHoldReason' => 'required|string|min:3',
        ], [
            'onHoldReason.required' => __('You must enter a reason to put the order On Hold.'),
            'onHoldReason.min' => __('The reason must be at least 3 characters.'),
        ]);

        $order = Order::findOrFail($this->pendingOnHoldOrderId);
        $previousStatus = $order->core_status;
        $newStatus = CoreStatus::ON_HOLD;

        $order->update(['core_status' => $newStatus]);

        // Run local workflow automations
        app(AutomationEngine::class)->handleStatusChanged($order, $previousStatus, $newStatus, auth()->user()?->name ?? __('User'));

        // Log event in OrderEvent with reason
        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'MOVED_TO_ON_HOLD',
            'actor' => auth()->user()?->name ?? __('User'),
            'previous_value' => $previousStatus->value,
            'new_value' => $newStatus->value,
            'metadata' => [
                'reason' => $this->onHoldReason,
                'comment' => $this->onHoldReason,
            ],
        ]);

        $freshOrder = $order->fresh();
        if ($freshOrder && $freshOrder->trello_card_id) {
            try {
                $pushedTitle = OrderTitleParserService::buildTitle($freshOrder);
                $pushed = app(TrelloSyncService::class)->updateCardOnTrello($freshOrder);
                if ($pushed) {
                    $freshOrder->update(['trello_title' => $pushedTitle]);
                }
            } catch (\Throwable $e) {
            }
        }

        $this->showOnHoldModal = false;
        $this->pendingOnHoldOrderId = null;
        $this->onHoldReason = '';

        $this->syncChangedColumns();
        $this->dispatch('order-updated');
        session()->flash('message', __('Order :name moved to On Hold.', ['name' => $order->company_name]));
    }

    public function cancelOnHold()
    {
        $this->showOnHoldModal = false;
        $this->pendingOnHoldOrderId = null;
        $this->onHoldReason = '';
    }

    public function cancelResume()
    {
        $this->showResumeModal = false;
        $this->pendingResumeOrderId = null;
        $this->pendingResumeNewStatus = null;
        $this->resumeReason = '';
    }

    public function confirmResume()
    {
        if (! $this->pendingResumeOrderId || ! $this->pendingResumeNewStatus) {
            return;
        }

        $this->validate([
            'resumeReason' => 'required|string|min:3',
        ], [
            'resumeReason.required' => __('You must enter a reason to resume the order.'),
            'resumeReason.min' => __('The reason must be at least 3 characters.'),
        ]);

        $order = Order::findOrFail($this->pendingResumeOrderId);
        $previousStatus = $order->core_status;
        $newStatus = CoreStatus::from($this->pendingResumeNewStatus);

        if ($newStatus === CoreStatus::EN_PRODUCCION && ! $order->approved) {
            $this->showResumeModal = false;
            $this->dispatch('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);
            $this->dispatch('toast', message: __('The order requires approval before being sent to Production.'), type: 'info');

            return;
        }

        if ($newStatus === CoreStatus::EN_PRODUCCION) {
            $order->update([
                'core_status' => $newStatus,
                'substatus' => Substatus::ENVIADO_EN_ALTA,
                'done_today' => true,
            ]);
        } elseif (in_array($newStatus, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE], true)) {
            $order->update([
                'core_status' => $newStatus,
                'done_today' => true,
            ]);
        } else {
            $order->update(['core_status' => $newStatus]);
        }

        app(AutomationEngine::class)->handleStatusChanged($order, $previousStatus, $newStatus, auth()->user()?->name ?? __('User'));

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'RESUMED_FROM_ON_HOLD',
            'actor' => auth()->user()?->name ?? __('User'),
            'previous_value' => $previousStatus ? $previousStatus->value : null,
            'new_value' => $newStatus->value,
            'metadata' => [
                'reason' => $this->resumeReason,
                'comment' => $this->resumeReason,
            ],
        ]);

        $freshOrder = $order->fresh();
        if ($freshOrder && $freshOrder->trello_card_id) {
            try {
                $pushedTitle = OrderTitleParserService::buildTitle($freshOrder);
                $pushed = app(TrelloSyncService::class)->updateCardOnTrello($freshOrder);
                if ($pushed) {
                    $freshOrder->update(['trello_title' => $pushedTitle]);
                }
            } catch (\Throwable $e) {
            }
        }

        $this->cancelResume();
        $this->syncChangedColumns();
        $this->dispatch('order-updated');
        session()->flash('message', __('Order :name resumed.', ['name' => $order->company_name]));
    }

    public function duplicateOrder($orderId)
    {
        // Dispatch to CreateOrderModal's open-duplicate-order so user can edit before saving
        $this->dispatch('open-duplicate-order', orderId: $orderId);
    }

    public function trashOrder($orderId)
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        if ($user && ! $user->canDeleteOrders()) {
            abort(403, __('You do not have permission to send orders to the trash.'));
        }

        $order->delete(); // soft delete

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_TRASHED',
            'actor' => auth()->user()?->name ?? __('User'),
            'previous_value' => $order->core_status?->value,
            'new_value' => 'TRASHED',
            'metadata' => ['comment' => __('Order moved to the trash.')],
        ]);

        $this->syncChangedColumns();
        $this->dispatch('order-updated');
        session()->flash('message', __('Order \':name\' sent to the trash.', ['name' => $order->company_name]));
    }

    public function toggleTaskComplete($taskId)
    {
        $task = RelatedTask::findOrFail($taskId);
        if ($task->isDone()) {
            $task->update(['status' => 'todo', 'completed_at' => null]);
        } else {
            $task->update(['status' => 'done', 'completed_at' => now()]);
        }

        $this->syncChangedColumns();
        $this->dispatch('order-updated');
        session()->flash('message', __('Task \':title\' updated.', ['title' => $task->title]));
    }

    public function deleteTask($taskId)
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $taskTitle = $task->title;
            $task->delete();
            $this->syncChangedColumns();
            $this->dispatch('order-updated');
            session()->flash('message', __('Task \':title\' deleted.', ['title' => $taskTitle]));
        }
    }

    #[Computed]
    public function orders(): Collection
    {
        return $this->ordersQuery()->get();
    }

    #[Computed]
    public function designers(): Collection
    {
        return Designer::where('active', true)->internal()->get();
    }

    #[Computed]
    public function existingCompanies(): Collection
    {
        return Order::inWorkspace()
            ->whereNotNull('company_name')
            ->where('company_name', '!=', '')
            ->distinct()
            ->orderBy('company_name')
            ->pluck('company_name');
    }

    #[Computed]
    public function existingResponsibles(): Collection
    {
        return Order::inWorkspace()
            ->whereNotNull('responsible_person')
            ->where('responsible_person', '!=', '')
            ->distinct()
            ->orderBy('responsible_person')
            ->pluck('responsible_person');
    }

    #[Computed]
    public function newOrdersCount(): int
    {
        return Order::inBacklog()->newFromTrello()->count();
    }

    #[Computed]
    public function archivedCount(): int
    {
        return Order::archived()->count();
    }

    public function render()
    {
        $allColumns = $this->allColumns();

        $columns = match ($this->columnGroup) {
            'incoming' => array_merge([CoreStatus::ENTRANTE], CoreStatus::designerQueueStatuses()),
            'in_progress' => [
                CoreStatus::TO_DO_TODAY,
                CoreStatus::ENVIADO_A_CAMILA,
                CoreStatus::ENVIADO_AL_CLIENTE,
            ],
            'final' => [
                CoreStatus::ON_HOLD,
                CoreStatus::EN_PRODUCCION,
                CoreStatus::ARCHIVED,
            ],
            default => $allColumns,
        };

        return view('livewire.kanban.board', [
            'columns' => $columns,
            'allColumns' => $allColumns,
            'archivedSubstatuses' => SubstatusModel::getArchivedSubstatuses(),
            'pendingArchiveOrder' => $this->pendingArchiveOrderId ? Order::find($this->pendingArchiveOrderId) : null,
        ])->layout('components.layouts.app', ['title' => __('Kanban board - ').config('app.name')]);
    }
}
