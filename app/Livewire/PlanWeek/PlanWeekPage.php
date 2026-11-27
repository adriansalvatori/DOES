<?php

namespace App\Livewire\PlanWeek;

use App\Application\Delivery\Actions\AddTask;
use App\Application\Delivery\Actions\CompleteTask;
use App\Application\Delivery\Actions\PlanDeliverable;
use App\Application\Delivery\Actions\ReopenTask;
use App\Application\Delivery\Actions\ScheduleTasks;
use App\Application\Delivery\Actions\UnplanDeliverable;
use App\Application\Delivery\Data\PlanWeek;
use App\Application\Delivery\Queries\PlanWeekQuery;
use App\Application\Delivery\Queries\TaskTemplateOptions;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\TaskTemplate;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;
use App\Support\TitleCase;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The weekly planner. Each card is a task on a person's day (Monday to Friday plus Next week). Drag to move or
 * reorder, tick to finish, select several for bulk moves, ⌘Z to undo, "+" to add a task in place. Managers plan
 * everyone and pull work from the backlog; designers plan their own row. Every change goes through the actions.
 */
#[Layout('components.layouts.app')]
class PlanWeekPage extends Component
{
    public const UNDO_DEPTH = 20;

    public const LAYOUTS = ['days', 'people'];

    #[Url(as: 'week')]
    public string $weekStart = '';

    #[Url(as: 'designer')]
    public string $designerFilter = '';

    #[Url(as: 'layout')]
    public string $layout = '';

    #[Url(as: 'sort')]
    public string $sort = PlanWeekQuery::SORT_MANUAL;

    public string $search = '';

    /**
     * What ⌘Z puts back, newest last.
     *
     * @var list<array{type: string, message: string, payload: array<string, mixed>}>
     */
    public array $undoStack = [];

    public function mount(): void
    {
        $viewer = auth()->user();
        abort_unless($viewer !== null && ($viewer->designs() || Roles::isManager($viewer)), 403);

        $this->weekStart = $this->mondayOf($this->weekStart !== '' ? $this->weekStart : null)->toDateString();
        $this->layout = in_array($this->layout, self::LAYOUTS, true) ? $this->layout : session('plan-week.layout', 'days');

        if ($this->designerFilter === '') {
            $this->designerFilter = (string) session('plan-week.designer', Roles::isManager($viewer) ? '' : (string) $viewer->getKey());
        }
    }

    public function updatedDesignerFilter(): void
    {
        session(['plan-week.designer' => $this->designerFilter]);
    }

    public function updatedLayout(): void
    {
        $this->layout = in_array($this->layout, self::LAYOUTS, true) ? $this->layout : 'days';
        session(['plan-week.layout' => $this->layout]);
    }

    public function updatedSort(): void
    {
        $this->sort = $this->sort === PlanWeekQuery::SORT_PRIORITY ? PlanWeekQuery::SORT_PRIORITY : PlanWeekQuery::SORT_MANUAL;
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Roles::isManager(auth()->user());
    }

    /**
     * Whether the viewer may change this row: managers any, designers their own.
     */
    public function canEditRow(?User $person): bool
    {
        $viewer = auth()->user();

        return $person !== null && ($this->canEdit || ($viewer->designs() && $person->is($viewer)));
    }

    #[Computed]
    public function plan(): PlanWeek
    {
        return app(PlanWeekQuery::class)->run($this->mondayOf($this->weekStart), $this->sort);
    }

    /**
     * @return list<array{assignee: ?User, limit: int, cells: array<string, array<string, mixed>>}>
     */
    #[Computed]
    public function rows(): array
    {
        $rows = $this->plan->rows;

        if (! $this->canEdit) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['assignee'] !== null));
        }

        if ($this->designerFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => (string) $row['assignee']?->getKey() === $this->designerFilter));
        }

        return $rows;
    }

    /**
     * Work to plan, after the search. Managers only.
     *
     * @return Collection<int, Deliverable>
     */
    #[Computed]
    public function backlog(): Collection
    {
        if (! $this->canEdit) {
            return collect();
        }

        return $this->plan->backlog
            ->filter(fn (Deliverable $deliverable): bool => $this->matches($deliverable))
            ->when($this->designerFilter !== '', fn (Collection $cards) => $cards->filter(fn (Deliverable $deliverable): bool => (string) $deliverable->assignee_id === $this->designerFilter))
            ->values();
    }

    /**
     * @return Collection<int, Deliverable>
     */
    #[Computed]
    public function blockedBacklog(): Collection
    {
        return $this->canEdit ? $this->plan->blockedBacklog->filter(fn (Deliverable $deliverable): bool => $this->matches($deliverable))->values() : collect();
    }

    /**
     * Everyone with a row this week (for the tabs and the reassign menu).
     *
     * @return list<User>
     */
    #[Computed]
    public function people(): array
    {
        return array_values(array_filter(array_map(fn (array $row): ?User => $row['assignee'], $this->plan->rows)));
    }

    /**
     * Open cards this week whose work order promise is late or due within two weekdays.
     *
     * @return list<array{deliverableId: int, label: string, promised: string, overdue: bool}>
     */
    #[Computed]
    public function alerts(): array
    {
        $today = CarbonImmutable::today();
        $soon = $today->addWeekdays(2);
        $alerts = [];

        foreach ($this->rows as $row) {
            foreach ($row['cells'] as $cell) {
                $deliverables = $cell['tasks']->toBase()->reject(fn (Task $task): bool => $task->isDone())->map->deliverable->merge($cell['deliverables']->all());

                foreach ($deliverables as $deliverable) {
                    $promised = $deliverable->workOrder?->promised_date;

                    if ($promised !== null && $promised->lte($soon) && ! isset($alerts[$deliverable->id])) {
                        $alerts[$deliverable->id] = [
                            'deliverableId' => $deliverable->id,
                            'label' => $this->labelFor($deliverable),
                            'promised' => $promised->toDateString(),
                            'overdue' => $promised->lt($today),
                        ];
                    }
                }
            }
        }

        return collect($alerts)->sortBy('promised')->values()->all();
    }

    /**
     * Task templates for the quick-add title suggestions.
     *
     * @return list<string>
     */
    #[Computed]
    public function templateNames(): array
    {
        return collect(app(TaskTemplateOptions::class)->asOptions(app()->getLocale()))->pluck('name')->values()->all();
    }

    public function previousWeek(): void
    {
        $this->weekStart = $this->mondayOf($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = $this->mondayOf($this->weekStart)->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->weekStart = $this->mondayOf(null)->toDateString();
    }

    public function goToDate(string $date): void
    {
        $this->weekStart = $this->mondayOf($date)->toDateString();
    }

    /**
     * A drop: the full order of the target day, already including the moved tasks.
     *
     * @param  list<int>  $orderedTaskIds
     */
    public function placeTasks(array $orderedTaskIds, int $assigneeId, string $column): void
    {
        $person = User::query()->findOrFail($assigneeId);
        $ids = array_values(array_map('intval', $orderedTaskIds));

        $this->attempt(function () use ($ids, $person, $column): void {
            if ($column === PlanWeek::NEXT_WEEK) {
                // Next week keeps each task's own day there; only tasks coming from elsewhere land on its Monday.
                $nextWeek = $this->plan->nextWeek;
                $ids = Task::query()->whereKey($ids)->get()
                    ->reject(fn (Task $task): bool => $task->scheduled_date !== null && $task->scheduled_date->betweenIncluded($nextWeek, $nextWeek->addDays(6)) && $task->assignee_user_id === $person->getKey())
                    ->modelKeys();
                $previous = $this->scheduleAppending($ids, $person, $nextWeek);
            } else {
                $previous = app(ScheduleTasks::class)($ids, $person, CarbonImmutable::parse($column), auth()->user());
            }

            $this->remember('schedule', __('Plan updated.'), ['previous' => $previous]);
        });
    }

    /**
     * Ticks a task done, or reopens it.
     */
    public function toggleTask(int $taskId): void
    {
        $task = Task::query()->with('deliverable')->findOrFail($taskId);

        $this->attempt(function () use ($task): void {
            Gate::forUser(auth()->user())->authorize('plan', [$task]);
            $wasDone = $task->isDone();
            $wasDone ? app(ReopenTask::class)($task, auth()->user()) : app(CompleteTask::class)($task, auth()->user());
            $this->remember('toggle', $wasDone ? __('Task reopened.') : __('Task done.'), ['ids' => [$task->getKey()], 'done' => ! $wasDone]);
        });
    }

    /**
     * Moves the selected tasks to today, tomorrow or next week, each staying with its own person.
     *
     * @param  list<int>  $taskIds
     */
    public function bulkMove(array $taskIds, string $target): void
    {
        $day = match ($target) {
            'today' => $this->workday(CarbonImmutable::today()),
            'tomorrow' => $this->workday(CarbonImmutable::today()->addDay()),
            'next_week' => CarbonImmutable::today()->startOfWeek(CarbonInterface::MONDAY)->addWeek(),
            default => null,
        };

        if ($day === null) {
            return;
        }

        $this->attempt(function () use ($taskIds, $day): void {
            $previous = [];
            $tasks = Task::query()->with('deliverable')->whereKey($taskIds)->get();

            foreach ($tasks->groupBy(fn (Task $task): int => (int) $task->effectiveAssigneeId()) as $personId => $group) {
                $person = User::query()->find($personId);

                if ($person !== null) {
                    $previous = [...$previous, ...$this->scheduleAppending($group->modelKeys(), $person, $day)];
                }
            }

            $this->remember('schedule', trans_choice('{1} Moved 1 task.|[2,*] Moved :count tasks.', $tasks->count(), ['count' => $tasks->count()]), ['previous' => $previous]);
        });
    }

    /**
     * Hands the selected tasks to another person on the same days. Managers only (the policy refuses designers).
     *
     * @param  list<int>  $taskIds
     */
    public function bulkAssign(array $taskIds, int $assigneeId): void
    {
        $person = User::query()->findOrFail($assigneeId);

        $this->attempt(function () use ($taskIds, $person): void {
            $previous = [];
            $tasks = Task::query()->whereKey($taskIds)->whereNotNull('scheduled_date')->get();

            foreach ($tasks->groupBy(fn (Task $task): string => $task->scheduled_date->toDateString()) as $date => $group) {
                $previous = [...$previous, ...$this->scheduleAppending($group->modelKeys(), $person, CarbonImmutable::parse($date))];
            }

            $this->remember('schedule', __('Handed to :name.', ['name' => $person->name]), ['previous' => $previous]);
        });
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function bulkComplete(array $taskIds): void
    {
        $this->attempt(function () use ($taskIds): void {
            $done = [];

            foreach (Task::query()->with('deliverable')->whereKey($taskIds)->get() as $task) {
                Gate::forUser(auth()->user())->authorize('plan', [$task]);

                if (! $task->isDone()) {
                    app(CompleteTask::class)($task, auth()->user());
                    $done[] = $task->getKey();
                }
            }

            $this->remember('toggle', trans_choice('{1} 1 task done.|[2,*] :count tasks done.', count($done), ['count' => count($done)]), ['ids' => $done, 'done' => true]);
        });
    }

    /**
     * Takes a task off the plan: it goes back to the backlog with its deliverable.
     */
    public function unscheduleTask(int $taskId): void
    {
        $this->attempt(function () use ($taskId): void {
            $previous = app(ScheduleTasks::class)([$taskId], null, null, auth()->user());
            $this->remember('schedule', __('Back to the backlog.'), ['previous' => $previous]);
        });
    }

    /**
     * Drops a deliverable from the backlog (or moves a card without tasks) onto a person's day. Its open tasks
     * without a day follow it. The WIP limit only warns. Returns whether the deliverable still needs a first task.
     *
     * @return array{ok: bool, needsTask?: bool, label?: string}
     */
    public function planDeliverable(int $deliverableId, int $assigneeId, string $column): array
    {
        $deliverable = Deliverable::query()->findOrFail($deliverableId);
        $person = User::query()->findOrFail($assigneeId);
        $day = $column === PlanWeek::NEXT_WEEK ? $this->plan->nextWeek : CarbonImmutable::parse($column);

        return $this->attempt(function () use ($deliverable, $person, $day): array {
            abort_unless($this->canEdit, 403);
            $before = ['assignee_id' => $deliverable->assignee_id, 'planned_for' => $deliverable->planned_for?->toDateString(), 'status' => $deliverable->status->value];

            app(PlanDeliverable::class)($deliverable, $person, $day, auth()->user(), true);
            $unscheduled = $deliverable->tasks()->where('status', '!=', 'done')->whereNull('scheduled_date')->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            $previous = $this->scheduleAppending($unscheduled, $person, $day);
            $needsTask = ! $deliverable->tasks()->where('status', '!=', 'done')->exists();

            $this->remember('plan', __('Planned.'), ['deliverableId' => $deliverable->getKey(), 'before' => $before, 'previous' => $previous]);

            return ['ok' => true, 'needsTask' => $needsTask, 'label' => $this->labelFor($deliverable)];
        }) ?? ['ok' => false];
    }

    public function unplanDeliverable(int $deliverableId): void
    {
        $deliverable = Deliverable::query()->findOrFail($deliverableId);

        $this->attempt(function () use ($deliverable): void {
            $before = ['assignee_id' => $deliverable->assignee_id, 'planned_for' => $deliverable->planned_for?->toDateString(), 'status' => $deliverable->status->value];
            app(UnplanDeliverable::class)($deliverable, auth()->user());
            $this->remember('plan', __('Back to the backlog.'), ['deliverableId' => $deliverable->getKey(), 'before' => $before, 'previous' => []]);
        });
    }

    /**
     * Adds a task in place: on that person's day, for a deliverable. A title that matches a template takes its kind.
     *
     * @return array{ok: bool}
     */
    public function quickAdd(int $deliverableId, string $title, int $assigneeId, string $column): array
    {
        $title = trim($title);

        if ($title === '') {
            return ['ok' => false];
        }

        $deliverable = Deliverable::query()->findOrFail($deliverableId);
        $person = User::query()->findOrFail($assigneeId);
        $day = $column === PlanWeek::NEXT_WEEK ? $this->plan->nextWeek : CarbonImmutable::parse($column);

        return $this->attempt(function () use ($deliverable, $person, $day, $title): array {
            if (! $this->canEditRow($person)) {
                throw new AuthorizationException;
            }

            $template = TaskTemplate::query()->active()->get()->first(fn (TaskTemplate $each): bool => mb_strtolower($each->nameFor()) === mb_strtolower($title));
            $kind = $template?->taskKind() ?? TaskKind::WORK;
            $task = app(AddTask::class)($deliverable, $template?->nameFor() ?? $title, $kind, $kind === TaskKind::WORK ? null : $template?->advances_to, null, auth()->user());

            if ($this->canEdit && $deliverable->status === DeliverableStatus::READY && $deliverable->planned_for === null) {
                app(PlanDeliverable::class)($deliverable, $person, $day, auth()->user(), true);
            }

            $this->scheduleAppending([$task->getKey()], $person, $day);
            $this->dispatch('toast', message: __('Task added.'));

            return ['ok' => true];
        }) ?? ['ok' => false];
    }

    /**
     * Open deliverables for the quick-add picker: WO number, client or piece name.
     *
     * @return list<array{id: int, label: string, title: string, piece: string}>
     */
    #[Renderless]
    public function searchDeliverables(string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';

        return Deliverable::query()
            ->inWorkspace()
            ->active()
            ->where(fn (Builder $any) => $any
                ->where('task_name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhereHas('workOrder', fn (Builder $workOrders) => $workOrders->where('number', 'like', $like)->orWhere('company_name', 'like', $like)
                    ->orWhereHas('client', fn (Builder $clients) => $clients->where('name', 'like', $like))))
            ->with('workOrder.client')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Deliverable $deliverable): array => [
                'id' => $deliverable->id,
                'label' => $this->labelFor($deliverable),
                'title' => trim(($deliverable->workOrder?->number ?? '').' · '.TitleCase::from($deliverable->workOrder?->displayName() ?: $deliverable->company_name), ' ·'),
                'piece' => (string) $deliverable->task_name,
            ])
            ->all();
    }

    /**
     * Puts back the last change made on this screen.
     */
    public function undo(): void
    {
        $entry = array_pop($this->undoStack);

        if ($entry === null) {
            $this->dispatch('toast', message: __('Nothing to undo.'), type: 'info');

            return;
        }

        $this->attempt(function () use ($entry): void {
            $actor = auth()->user();
            $payload = $entry['payload'];

            if ($entry['type'] === 'toggle') {
                foreach (Task::query()->with('deliverable')->whereKey($payload['ids'])->get() as $task) {
                    $payload['done'] ? app(ReopenTask::class)($task, $actor) : app(CompleteTask::class)($task, $actor);
                }
            }

            if ($entry['type'] === 'plan') {
                $this->undoPlan($payload);
            }

            if (($payload['previous'] ?? []) !== []) {
                app(ScheduleTasks::class)->restore($payload['previous'], $actor);
            }

            $this->dispatch('toast', message: __('Undone.'), type: 'info');
        });
    }

    /**
     * The panel changed a deliverable: reload.
     */
    #[On('deliverable-changed')]
    public function refreshAfterPanelChange(): void
    {
        $this->forgetData();
    }

    public function render()
    {
        return view('livewire.plan-week.plan-week-page', [
            'weekStartDate' => $this->mondayOf($this->weekStart),
            'isCurrentWeek' => $this->mondayOf($this->weekStart)->equalTo($this->mondayOf(null)),
        ])->title(__('Plan week'));
    }

    /**
     * Puts tasks at the end of a person's day, after what is already there.
     *
     * @param  list<int>  $taskIds
     * @return list<array{id: int, assignee_user_id: ?int, scheduled_date: ?string, sort_order: int}>
     */
    private function scheduleAppending(array $taskIds, User $person, CarbonInterface $day): array
    {
        if ($taskIds === []) {
            return [];
        }

        $already = Task::query()
            ->where('scheduled_date', $day->toDateString())
            ->where(fn (Builder $mine) => $mine->where('assignee_user_id', $person->getKey())
                ->orWhere(fn (Builder $inherited) => $inherited->whereNull('assignee_user_id')->whereHas('deliverable', fn (Builder $deliverables) => $deliverables->where('assignee_id', $person->getKey()))))
            ->whereKeyNot($taskIds)
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('id')->all();

        return app(ScheduleTasks::class)([...$already, ...$taskIds], $person, $day, auth()->user());
    }

    /**
     * @param  array{deliverableId: int, before: array{assignee_id: ?int, planned_for: ?string, status: string}}  $payload
     */
    private function undoPlan(array $payload): void
    {
        $deliverable = Deliverable::query()->findOrFail($payload['deliverableId']);
        $before = $payload['before'];
        $person = $before['assignee_id'] !== null ? User::query()->find($before['assignee_id']) : null;

        if ($before['planned_for'] !== null && $person !== null) {
            app(PlanDeliverable::class)($deliverable, $person, CarbonImmutable::parse($before['planned_for']), auth()->user(), true);
        } elseif ($deliverable->planned_for !== null) {
            app(UnplanDeliverable::class)($deliverable, auth()->user());
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function remember(string $type, string $message, array $payload): void
    {
        $this->undoStack[] = ['type' => $type, 'message' => $message, 'payload' => $payload];
        $this->undoStack = array_slice($this->undoStack, -self::UNDO_DEPTH);
        $this->dispatch('plan-week-undoable', message: $message);
    }

    /**
     * Runs a change; a refusal becomes a toast and the screen reloads either way.
     *
     * @template T
     *
     * @param  callable(): T  $change
     * @return T|null
     */
    private function attempt(callable $change): mixed
    {
        try {
            return $change();
        } catch (WorkflowException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        } catch (AuthorizationException) {
            $this->dispatch('toast', message: __('You can only plan your own work.'), type: 'error');
        } finally {
            $this->forgetData();
        }

        return null;
    }

    /**
     * "WO 123 · Client · Piece", for pickers and lists.
     */
    private function labelFor(Deliverable $deliverable): string
    {
        return trim(($deliverable->workOrder?->number ?? '').' · '.TitleCase::from($deliverable->workOrder?->displayName() ?: $deliverable->company_name).' · '.$deliverable->task_name, ' ·');
    }

    private function forgetData(): void
    {
        unset($this->plan, $this->rows, $this->backlog, $this->blockedBacklog, $this->people, $this->alerts);
    }

    private function workday(CarbonImmutable $day): CarbonImmutable
    {
        return $day->isWeekend() ? $day->next(CarbonInterface::MONDAY) : $day;
    }

    private function mondayOf(?string $date): CarbonImmutable
    {
        try {
            $day = $date !== null ? CarbonImmutable::parse($date) : CarbonImmutable::now();
        } catch (\Throwable) {
            $day = CarbonImmutable::now();
        }

        return $day->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    private function matches(Deliverable $deliverable): bool
    {
        $term = mb_strtolower(trim($this->search));

        if ($term === '') {
            return true;
        }

        return str_contains(mb_strtolower(implode(' ', [
            $deliverable->workOrder?->number,
            $deliverable->wo_number,
            $deliverable->company_name,
            $deliverable->workOrder?->displayName(),
            $deliverable->task_name,
        ])), $term);
    }
}
