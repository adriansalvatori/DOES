<?php

namespace App\Livewire\Merge;

use App\Application\Delivery\Actions\MergeDeliverables;
use App\Application\Delivery\Actions\MergeTasks;
use App\Application\Delivery\Actions\MergeWorkOrders;
use App\Application\Delivery\Queries\MergePreviewQuery;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One modal for merging work orders, deliverables (samples included) or tasks. Pick the main record, then for every
 * field where the records disagree pick whose value to keep. Fields that already match are not asked about.
 * Mounted once in the app layout and opened with the `open-merge` event.
 */
class MergeModal extends Component
{
    public const SEARCH_LIMIT = 8;

    public bool $isOpen = false;

    public string $kind = '';

    /** @var list<int> */
    public array $ids = [];

    public ?int $mainId = null;

    /** @var array<string, string> Record id whose value wins, by field ('sum' adds up task points). */
    public array $choices = [];

    public string $search = '';

    public ?string $error = null;

    /**
     * @param  list<int|string>  $ids
     */
    #[On('open-merge')]
    public function open(string $kind, array $ids = []): void
    {
        if (! in_array($kind, MergePreviewQuery::KINDS, true)) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (! $this->mayMerge($kind, $ids)) {
            $this->dispatch('toast', message: __('You are not allowed to do this.'), type: 'error');

            return;
        }

        $this->kind = $kind;
        $this->ids = $ids;
        $this->mainId = $ids[0] ?? null;
        $this->search = '';
        $this->error = null;
        $this->resetChoices();
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->reset('kind', 'ids', 'mainId', 'choices', 'search', 'error');
    }

    /**
     * @return array{records: list<array{id: int, label: string, detail: string}>, fields: list<array{field: string, label: string, values: array<int, string>, conflict: bool}>, pointsSum: ?int, summary: string}
     */
    #[Computed]
    public function preview(): array
    {
        return app(MergePreviewQuery::class)->run($this->kind, $this->ids, $this->mainId);
    }

    /**
     * Cards to add when merging deliverables: any live card matching the search, other than the ones already in.
     *
     * @return Collection<int, Deliverable>
     */
    #[Computed]
    public function searchResults(): Collection
    {
        $term = trim($this->search);

        if ($this->kind !== MergePreviewQuery::DELIVERABLE || mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.$term.'%';

        return Deliverable::query()
            ->whereNotIn('id', $this->ids)
            ->where(fn (Builder $query) => $query
                ->where('task_name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhereHas('workOrder', fn (Builder $workOrders) => $workOrders->where('number', 'like', $like)))
            ->with('workOrder')
            ->orderByDesc('id')
            ->limit(self::SEARCH_LIMIT)
            ->get();
    }

    public function setMain(int $id): void
    {
        if (in_array($id, $this->ids, true)) {
            $this->mainId = $id;
            $this->resetChoices();
        }
    }

    public function addRecord(int $id): void
    {
        if ($this->kind === MergePreviewQuery::DELIVERABLE && ! in_array($id, $this->ids, true) && Deliverable::query()->whereKey($id)->exists()) {
            $this->ids[] = $id;
            $this->search = '';
            $this->resetChoices();
        }
    }

    public function removeRecord(int $id): void
    {
        $this->ids = array_values(array_filter($this->ids, fn (int $each): bool => $each !== $id));
        if ($this->mainId === $id) {
            $this->mainId = $this->ids[0] ?? null;
        }
        $this->resetChoices();
    }

    public function merge(): void
    {
        $this->error = null;
        $query = app(MergePreviewQuery::class);
        $records = $query->records($this->kind, $this->ids);

        if ($records->count() < 2 || ! $records->has($this->mainId)) {
            $this->error = __('Choose at least two records and the main one.');

            return;
        }

        $main = $records->get($this->mainId);
        $others = $records->except($this->mainId)->values()->all();
        $fields = [];
        foreach ($this->choices as $field => $choice) {
            if ($choice === 'sum') {
                $fields[$field] = (int) $records->sum($field);
            } elseif ($records->has((int) $choice) && (int) $choice !== $this->mainId) {
                $fields[$field] = $query->value($records->get((int) $choice), $field);
            }
        }

        try {
            $result = match ($this->kind) {
                MergePreviewQuery::WORK_ORDER => app(MergeWorkOrders::class)($main, $others, $fields, auth()->user()),
                MergePreviewQuery::DELIVERABLE => app(MergeDeliverables::class)($main, $others, $fields, auth()->user()),
                default => app(MergeTasks::class)($main, $others, $fields, auth()->user()),
            };
        } catch (WorkflowException $exception) {
            $this->error = $exception->getMessage();

            return;
        } catch (AuthorizationException) {
            $this->error = __('You are not allowed to do this.');

            return;
        }

        $kind = $this->kind;
        $count = count($others) + 1;
        $this->close();
        $this->dispatch('toast', message: trans_choice('{2} 2 records merged.|[3,*] :count records merged.', $count, ['count' => $count]));

        if ($kind === MergePreviewQuery::WORK_ORDER) {
            $this->redirectRoute('work-orders.show', $result, navigate: true);

            return;
        }

        $deliverableId = $kind === MergePreviewQuery::DELIVERABLE ? $result->getKey() : $result->order_id;
        $this->dispatch('deliverable-changed', deliverableId: $deliverableId);
        $this->dispatch('open-deliverable', deliverableId: $deliverableId);
    }

    public function render()
    {
        return view('livewire.merge.merge-modal');
    }

    private function resetChoices(): void
    {
        $this->choices = [];
        foreach (app(MergePreviewQuery::class)->run($this->kind, $this->ids, $this->mainId)['fields'] as $field) {
            if ($field['conflict']) {
                $this->choices[$field['field']] = (string) $this->mainId;
            }
        }
        unset($this->preview, $this->searchResults);
    }

    /**
     * @param  list<int>  $ids
     */
    private function mayMerge(string $kind, array $ids): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($kind !== MergePreviewQuery::TASK) {
            return Gate::forUser($user)->allows('merge', Deliverable::class);
        }

        $deliverable = Task::query()->whereKey($ids)->first()?->deliverable;

        return $deliverable !== null && Gate::forUser($user)->allows('manageTasks', $deliverable);
    }
}
