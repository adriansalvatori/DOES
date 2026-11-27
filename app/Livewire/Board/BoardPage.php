<?php

namespace App\Livewire\Board;

use App\Application\Delivery\Actions\BlockDeliverable;
use App\Application\Delivery\Actions\DuplicateDeliverable;
use App\Application\Delivery\Actions\LogReceipt;
use App\Application\Delivery\Actions\MarkProduced;
use App\Application\Delivery\Actions\MoveDeliverable;
use App\Application\Delivery\Actions\PullToProduction;
use App\Application\Delivery\Actions\PutOnHold;
use App\Application\Delivery\Actions\ResolveBlock;
use App\Application\Delivery\Actions\Resume;
use App\Application\Delivery\Actions\StartProduction;
use App\Application\Delivery\Actions\TrashDeliverable;
use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\Roles;
use App\Models\Client;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The board: twelve status columns in three area tabs. Each column is its own component; this page owns the
 * filters, the shared card menu and every write, which always goes through an Action. Actions never re-render the
 * page: they toast the outcome and ask the source and target columns to refresh.
 */
#[Layout('components.layouts.app')]
class BoardPage extends Component
{
    #[Url(as: 'area')]
    public string $area = 'design';

    #[Url(as: 'designer')]
    public string $designer = '';

    #[Url(as: 'company')]
    public string $company = '';

    /** @var list<string> */
    #[Url(as: 'flags')]
    public array $flags = [];

    #[Url(as: 'q', history: false)]
    public string $search = '';

    #[Url(as: 'group')]
    public string $groupBy = 'work_order';

    /** @var array<string, string> */
    public array $fingerprints = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->active, 403);

        $this->area = Area::tryFrom($this->area)?->value ?? Area::DESIGN->value;
        $this->groupBy = in_array($this->groupBy, ['work_order', 'designer', 'none'], true) ? $this->groupBy : 'work_order';
        $this->fingerprints = $this->currentFingerprints();
    }

    /**
     * Tells every mounted column about a changed filter.
     */
    public function updated(string $property): void
    {
        if (in_array(explode('.', $property)[0], ['designer', 'company', 'flags', 'search', 'groupBy'], true)) {
            $this->dispatch('board-filters-changed', filters: [
                'assigneeId' => $this->assigneeId,
                'clientId' => $this->clientId,
                'flags' => array_values($this->flags),
                'search' => $this->search,
                'groupBy' => $this->effectiveGroupBy(),
            ]);
        }
    }

    /**
     * Groupings offered in the active tab. Grouping by designer exists on the design tab only.
     *
     * @return array<string, string>
     */
    public function groupingOptions(): array
    {
        return array_filter([
            'work_order' => __('Group by: work order'),
            'designer' => $this->area === Area::DESIGN->value ? __('Group by: designer') : null,
            'none' => __('No grouping'),
        ]);
    }

    /**
     * The grouping the columns use: a choice the active tab does not offer falls back to work order.
     */
    public function effectiveGroupBy(): string
    {
        return array_key_exists($this->groupBy, $this->groupingOptions()) ? $this->groupBy : 'work_order';
    }

    public function updatedArea(): void
    {
        $this->area = Area::tryFrom($this->area)?->value ?? Area::DESIGN->value;
        $this->fingerprints = $this->currentFingerprints();
    }

    /**
     * The statuses shown as columns in the active tab. Production also lists what is ready to be pulled.
     *
     * @return list<DeliverableStatus>
     */
    #[Computed]
    public function columnStatuses(): array
    {
        $area = Area::from($this->area);

        return $area === Area::PRODUCTION
            ? [DeliverableStatus::READY_FOR_PRODUCTION, ...DeliverableStatus::inArea(Area::PRODUCTION)]
            : DeliverableStatus::inArea($area);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function designers(): Collection
    {
        return User::query()
            ->designTeam()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Companies that have work in flight, so the filter stays short.
     *
     * @return Collection<int, Client>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Client::query()
            ->whereIn('id', WorkOrder::query()
                ->whereHas('deliverables', fn ($deliverables) => $deliverables->active())
                ->select('client_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function assigneeId(): ?int
    {
        return $this->designer !== '' ? (int) $this->designer : null;
    }

    #[Computed]
    public function clientId(): ?int
    {
        return $this->company !== '' ? (int) $this->company : null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function flagOptions(): array
    {
        return array_map(fn (DeliverableFlag $flag): array => ['value' => $flag->value, 'label' => $flag->label()], DeliverableFlag::cases());
    }

    #[Renderless]
    public function move(int $deliverableId, string $status): void
    {
        $target = DeliverableStatus::tryFrom($status);

        if ($target === null) {
            return;
        }

        $this->run($deliverableId, fn (Deliverable $deliverable, User $actor) => app(MoveDeliverable::class)($deliverable, $target, $actor), __('Moved to :status.', ['status' => $target->label()]));
    }

    #[Renderless]
    public function block(int $deliverableId, string $reason, string $note = ''): void
    {
        $blockReason = BlockReason::tryFrom($reason);

        if ($blockReason === null) {
            return;
        }

        $this->run($deliverableId, fn (Deliverable $deliverable, User $actor) => app(BlockDeliverable::class)($deliverable, $blockReason, $this->noteOrNull($note), $actor), __('Blocked.'));
    }

    #[Renderless]
    public function putOnHold(int $deliverableId, string $reason, string $note = ''): void
    {
        $holdReason = HoldReason::tryFrom($reason);

        if ($holdReason === null) {
            return;
        }

        $this->run($deliverableId, fn (Deliverable $deliverable, User $actor) => app(PutOnHold::class)($deliverable, $holdReason, $this->noteOrNull($note), $actor), __('Put on hold.'));
    }

    #[Renderless]
    public function resume(int $deliverableId): void
    {
        $this->run($deliverableId, fn (Deliverable $deliverable, User $actor) => app(Resume::class)($deliverable, $actor), __('Resumed.'));
    }

    #[Renderless]
    public function resolveBlock(int $deliverableId, string $note = ''): void
    {
        $this->run($deliverableId, function (Deliverable $deliverable, User $actor) use ($note): void {
            $block = $deliverable->openBlock;

            if ($block === null) {
                throw WorkflowException::refused('This deliverable is not blocked.');
            }

            app(ResolveBlock::class)($block, $this->noteOrNull($note), $actor);
        }, __('Block resolved.'));
    }

    #[Renderless]
    public function duplicate(int $deliverableId): void
    {
        $this->run($deliverableId, function (Deliverable $deliverable, User $actor): void {
            $copy = app(DuplicateDeliverable::class)($deliverable, $actor);
            $this->dispatch('board-refresh.'.$copy->status->value);
        }, __('Duplicated.'));
    }

    #[Renderless]
    public function trash(int $deliverableId): void
    {
        $this->run($deliverableId, fn (Deliverable $deliverable, User $actor) => app(TrashDeliverable::class)($deliverable, $actor), __('Moved to the trash.'));
    }

    #[Renderless]
    public function pullToProduction(int $deliverableId): void
    {
        $this->run($deliverableId, fn (Deliverable $deliverable, User $actor) => app(PullToProduction::class)($deliverable, $actor), __('Pulled into the production queue.'));
    }

    #[Renderless]
    public function startProduction(int $deliverableId): void
    {
        $this->run($deliverableId, fn (Deliverable $deliverable, User $actor) => app(StartProduction::class)($deliverable, $actor), __('Production started.'));
    }

    #[Renderless]
    public function markProduced(int $deliverableId): void
    {
        $this->run($deliverableId, fn (Deliverable $deliverable, User $actor) => app(MarkProduced::class)($deliverable, $actor), __('Marked as produced.'));
    }

    /**
     * Logs the receipt of one deliverable, or of everything waiting in a work order.
     *
     * @param  'deliverable'|'work_order'  $scope
     */
    #[Renderless]
    public function confirmReceived(string $scope, int $id, string $method, string $receivedOn = ''): void
    {
        $fulfillmentMethod = FulfillmentMethod::tryFrom($method);
        $actor = auth()->user();

        if ($fulfillmentMethod === null || $actor === null) {
            return;
        }

        try {
            $target = $scope === 'work_order' ? WorkOrder::query()->findOrFail($id) : Deliverable::query()->findOrFail($id);
            $receivedAt = $receivedOn !== '' ? CarbonImmutable::parse($receivedOn)->setTimeFrom(now()) : null;
            $receipts = app(LogReceipt::class)($target, $fulfillmentMethod, $actor, $receivedAt);
        } catch (WorkflowException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');

            return;
        } catch (AuthorizationException) {
            $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');

            return;
        }

        $this->refreshColumns([DeliverableStatus::PRODUCED, DeliverableStatus::FULFILLMENT, DeliverableStatus::DONE]);
        $this->dispatch('toast', message: trans_choice('{1} Receipt logged for :count deliverable.|[2,*] Receipt logged for :count deliverables.', $receipts->count()));
    }

    /**
     * The panel changed something: refresh only the columns whose count or last change moved.
     */
    #[On('deliverable-changed')]
    #[Renderless]
    public function refreshAfterPanelChange(): void
    {
        $this->checkForChanges();
    }

    /**
     * Cheap poll: one grouped read. Only a column whose count or last change moved is asked to refresh.
     */
    #[Renderless]
    public function checkForChanges(): void
    {
        $current = $this->currentFingerprints();

        foreach ($current as $status => $fingerprint) {
            if (($this->fingerprints[$status] ?? null) !== $fingerprint) {
                $this->dispatch('board-refresh.'.$status);
            }
        }

        $this->fingerprints = $current;
    }

    /**
     * @return array<string, string>
     */
    private function currentFingerprints(): array
    {
        $rows = Deliverable::query()
            ->inWorkspace()
            ->inStatus($this->columnStatuses)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total, MAX(updated_at) as changed_at')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $fingerprints = [];
        foreach ($this->columnStatuses as $status) {
            $row = $rows->get($status->value);
            $fingerprints[$status->value] = $row === null ? '0' : $row->total.'@'.$row->changed_at;
        }

        return $fingerprints;
    }

    /**
     * Runs one write through its Action, shows the domain refusal as a toast and refreshes only the columns involved.
     *
     * @param  Closure(Deliverable, User): mixed  $work
     */
    private function run(int $deliverableId, Closure $work, string $success): void
    {
        $actor = auth()->user();
        $deliverable = Deliverable::query()->with('openBlock')->find($deliverableId);

        if ($deliverable === null || $actor === null) {
            $this->dispatch('toast', message: __('This deliverable no longer exists.'), type: 'error');

            return;
        }

        $from = $deliverable->status;

        BoardSnapshot::flush();

        try {
            $work($deliverable, $actor);
        } catch (WorkflowException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
            $this->refreshColumns([$from]);

            return;
        } catch (AuthorizationException) {
            $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');
            $this->refreshColumns([$from]);

            return;
        }

        $this->refreshColumns([$from, $deliverable->fresh()?->status ?? $from]);
        $this->dispatch('toast', message: $success);
    }

    /**
     * @param  list<DeliverableStatus>  $statuses
     */
    private function refreshColumns(array $statuses): void
    {
        foreach (array_unique(array_map(fn (DeliverableStatus $status): string => $status->value, $statuses)) as $status) {
            $this->dispatch('board-refresh.'.$status);
        }
    }

    private function noteOrNull(string $note): ?string
    {
        return trim($note) !== '' ? trim($note) : null;
    }

    /**
     * What the shared card menu may offer this person.
     *
     * @return array<string, bool>
     */
    #[Computed]
    public function menuAbilities(): array
    {
        $viewer = auth()->user();
        $sample = new Deliverable;

        return [
            'move' => Gate::allows('move', $sample),
            'block' => Gate::allows('block', $sample),
            'manage' => Roles::isManager($viewer) || Roles::isCustomerService($viewer),
            'trash' => Roles::isManager($viewer),
            'duplicate' => Gate::allows('duplicate', $sample),
            'createWorkOrder' => Gate::allows('createWorkOrder', Deliverable::class),
        ];
    }

    public function render()
    {
        return view('livewire.board.board-page')->title(__('Board'));
    }
}
