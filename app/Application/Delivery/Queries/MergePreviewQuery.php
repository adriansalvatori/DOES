<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Actions\MergeDeliverables;
use App\Application\Delivery\Actions\MergeTasks;
use App\Application\Delivery\Actions\MergeWorkOrders;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use App\Support\TitleCase;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a merge would do: the records, every mergeable field with each record's value (shown as text) and whether the
 * values differ, plus what moves to the main record. Records come back in the order they were asked for.
 */
class MergePreviewQuery
{
    public const WORK_ORDER = 'work_order';

    public const DELIVERABLE = 'deliverable';

    public const TASK = 'task';

    public const KINDS = [self::WORK_ORDER, self::DELIVERABLE, self::TASK];

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Model> Live records keyed by id, in the asked order.
     */
    public function records(string $kind, array $ids): Collection
    {
        $query = match ($kind) {
            self::WORK_ORDER => WorkOrder::query()->with(['client', 'clientLocation', 'contact'])->withCount('deliverables'),
            self::DELIVERABLE => Deliverable::query()->with(['workOrder', 'assignee', 'reviewer'])->withCount('tasks'),
            default => Task::query()->with('deliverable'),
        };

        $found = $query->whereKey($ids)->get()->keyBy('id');

        return collect($ids)->filter(fn (int $id): bool => $found->has($id))->mapWithKeys(fn (int $id): array => [$id => $found[$id]]);
    }

    /**
     * @param  list<int>  $ids
     * @return array{records: list<array{id: int, label: string, detail: string}>, fields: list<array{field: string, label: string, values: array<int, string>, conflict: bool}>, pointsSum: ?int, summary: string}
     */
    public function run(string $kind, array $ids, ?int $mainId): array
    {
        $records = $this->records($kind, $ids);
        $main = $records->get($mainId) ?? $records->first();

        $fields = [];
        foreach ($this->fieldLabels($kind, $records) as $field => $label) {
            $values = $records->map(fn (Model $record): string => $this->display($kind, $record, $field))->all();
            $fields[] = [
                'field' => $field,
                'label' => $label,
                'values' => $values,
                'conflict' => count(array_unique($values)) > 1,
            ];
        }

        return [
            'records' => $records->map(fn (Model $record): array => [
                'id' => (int) $record->getKey(),
                'label' => $this->label($kind, $record),
                'detail' => $this->detail($kind, $record),
            ])->values()->all(),
            'fields' => $fields,
            'pointsSum' => $kind === self::TASK ? (int) $records->sum('points') : null,
            'summary' => $main === null ? '' : $this->summary($kind, $main, $records->except($main->getKey())),
        ];
    }

    /**
     * The value a record has for a field, as stored, to hand to the merge action.
     */
    public function value(Model $record, string $field): mixed
    {
        return $record->getAttribute($field);
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return array<string, string>
     */
    private function fieldLabels(string $kind, Collection $records): array
    {
        $labels = match ($kind) {
            self::WORK_ORDER => [
                'number' => __('WO number'), 'client_id' => __('Company'), 'company_name' => __('Company name'),
                'client_location_id' => __('Location'), 'contact_id' => __('Contact'), 'description' => __('Description'),
                'estimate_invoice_number' => __('Estimate / invoice'), 'promised_date' => __('Promised date'),
                'fulfillment_method' => __('Fulfillment method'), 'installation_types' => __('Installation'),
                'notes' => __('Notes'), 'drive_folder_id' => __('Drive folder'),
            ],
            self::DELIVERABLE => [
                'task_name' => __('Name'), 'size' => __('Size'), 'material' => __('Material'), 'quantity' => __('Quantity'),
                'files_url' => __('Files link'), 'trello_card_url' => __('Trello card'), 'assignee_id' => __('Assignee'),
                'reviewer_id' => __('Reviewer'), 'priority' => __('Priority'), 'due_target' => __('Due target'),
                'planned_for' => __('Planned for'), 'flag_codes' => __('Flags'), 'points' => __('Points'),
            ],
            default => ['title' => __('Title'), 'points' => __('Points'), 'due_date' => __('Due date'), 'status' => __('Status')],
        };

        if ($kind === self::DELIVERABLE && $records->contains(fn (Model $record): bool => $record->tasks_count > 0)) {
            unset($labels['points']);
        }

        $allowed = match ($kind) {
            self::WORK_ORDER => MergeWorkOrders::FIELDS,
            self::DELIVERABLE => MergeDeliverables::FIELDS,
            default => MergeTasks::FIELDS,
        };

        return array_intersect_key($labels, array_flip($allowed));
    }

    private function display(string $kind, Model $record, string $field): string
    {
        $value = $record->getAttribute($field);

        return match (true) {
            $value === null, $value === '' => '',
            $kind === self::WORK_ORDER && $field === 'client_id' => (string) TitleCase::from($record->client?->name),
            $kind === self::WORK_ORDER && $field === 'client_location_id' => (string) TitleCase::from($record->clientLocation?->name),
            $kind === self::WORK_ORDER && $field === 'contact_id' => (string) TitleCase::from($record->contact?->name),
            $field === 'assignee_id' => (string) $record->assignee?->name,
            $field === 'reviewer_id' => (string) $record->reviewer?->name,
            $field === 'flag_codes' => collect($value)->map(fn (DeliverableFlag $flag): string => $flag->label())->sort()->implode(', '),
            $field === 'installation_types' => implode(', ', (array) $value),
            $field === 'status' && $kind === self::TASK => $value === 'done' ? __('Done') : __('To do'),
            $value instanceof CarbonInterface => $value->translatedFormat('j M Y'),
            $value instanceof \BackedEnum && method_exists($value, 'label') => $value->label(),
            default => trim((string) $value),
        };
    }

    private function label(string $kind, Model $record): string
    {
        return match ($kind) {
            self::WORK_ORDER => trim(($record->number ?: __('No WO number')).' · '.TitleCase::from($record->displayName())),
            self::DELIVERABLE => (string) $record->task_name,
            default => $record->title_key ? __($record->title_key, $record->title_params ?? []) : (string) $record->title,
        };
    }

    private function detail(string $kind, Model $record): string
    {
        return match ($kind) {
            self::WORK_ORDER => trans_choice('{0} No deliverables|{1} :count deliverable|[2,*] :count deliverables', $record->deliverables_count, ['count' => $record->deliverables_count]),
            self::DELIVERABLE => trim(($record->workOrder?->number ?? '').' · '.$record->status->label().($record->isSample() ? ' · '.__('Production sample') : ''), ' ·'),
            default => $record->isDone() ? __('Done') : __('To do'),
        };
    }

    private function count(string $noun, int $count): string
    {
        return match ($noun) {
            'deliverable' => trans_choice('{1} :count deliverable|[0,*] :count deliverables', $count, ['count' => $count]),
            'phase' => trans_choice('{1} :count phase|[0,*] :count phases', $count, ['count' => $count]),
            'task' => trans_choice('{1} :count task|[0,*] :count tasks', $count, ['count' => $count]),
            'comment' => trans_choice('{1} :count comment|[0,*] :count comments', $count, ['count' => $count]),
            default => trans_choice('{1} :count file|[0,*] :count files', $count, ['count' => $count]),
        };
    }

    /**
     * @param  Collection<int, Model>  $others
     */
    private function summary(string $kind, Model $main, Collection $others): string
    {
        $ids = $others->keys()->all();

        return match ($kind) {
            self::WORK_ORDER => __('Moving to :main: :deliverables and :phases. The other work orders go to the trash, marked as merged.', [
                'deliverables' => $this->count('deliverable', Deliverable::query()->whereIn('work_order_id', $ids)->count()),
                'phases' => $this->count('phase', DB::table('phases')->whereIn('work_order_id', $ids)->count()),
                'main' => $main->number ?: __('the main work order'),
            ]),
            self::DELIVERABLE => __('Moving to the main card: :tasks, :comments and :files. It keeps its status and work order. The other cards go to the trash; their blocks, holds and alerts end.', [
                'tasks' => $this->count('task', DB::table('related_tasks')->whereIn('order_id', $ids)->whereNull('deleted_at')->count()),
                'comments' => $this->count('comment', DB::table('order_comments')->whereIn('order_id', $ids)->count()),
                'files' => $this->count('file', DB::table('media')->whereIn('order_id', $ids)->whereNull('deleted_at')->count()),
            ]),
            default => __('The main task keeps its kind; the other tasks go to the trash.'),
        };
    }
}
