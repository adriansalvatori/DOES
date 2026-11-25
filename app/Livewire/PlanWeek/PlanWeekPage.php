<?php

namespace App\Livewire\PlanWeek;

use App\Application\Delivery\Actions\PlanDeliverable;
use App\Application\Delivery\Actions\UnplanDeliverable;
use App\Application\Delivery\Data\PlanWeek;
use App\Application\Delivery\Queries\PlanWeekQuery;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\Roles;
use App\Domain\Scheduling\WipPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The design lead's weekly plan: the Ready backlog on the left, designers by Monday to Friday on the right.
 * Every change goes through PlanDeliverable or UnplanDeliverable. Designers see their own row, read-only.
 */
#[Layout('components.layouts.app')]
class PlanWeekPage extends Component
{
    #[Url(as: 'week')]
    public string $weekStart = '';

    public string $search = '';

    public string $designerFilter = '';

    public string $notice = '';

    /** @var array{deliverableId: int, assigneeId: int, date: string, message: string}|null */
    public ?array $pendingOverride = null;

    public function mount(): void
    {
        $viewer = auth()->user();
        abort_unless($viewer !== null && ($viewer->isDesigner() || Roles::isManager($viewer)), 403);

        $this->weekStart = $this->mondayOf($this->weekStart !== '' ? $this->weekStart : null)->toDateString();
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Roles::isManager(auth()->user());
    }

    #[Computed]
    public function plan(): PlanWeek
    {
        return app(PlanWeekQuery::class)->run($this->mondayOf($this->weekStart));
    }

    /**
     * Grid rows the viewer may see: everyone for a manager, only their own for a designer.
     *
     * @return list<array{assignee: User, limit: int, cells: array<string, array{cards: Collection<int, Deliverable>, used: int}>}>
     */
    #[Computed]
    public function rows(): array
    {
        $viewer = auth()->user();
        $rows = $this->plan->rows;

        if (! $this->canEdit) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['assignee']->is($viewer)));
            foreach ($rows as &$row) {
                foreach ($row['cells'] as $date => $cell) {
                    $cards = $cell['cards']->reject(fn (Deliverable $deliverable): bool => $deliverable->openBlock !== null)->values();
                    $row['cells'][$date] = ['cards' => $cards, 'used' => $cards->count()];
                }
            }
            unset($row);
        } elseif ($this->designerFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => (string) $row['assignee']->getKey() === $this->designerFilter));
        }

        return $rows;
    }

    /**
     * Ready backlog after the search and designer filters. Designers do not see it.
     *
     * @return Collection<int, Deliverable>
     */
    #[Computed]
    public function backlog(): Collection
    {
        if (! $this->canEdit) {
            return collect();
        }

        $term = mb_strtolower(trim($this->search));

        return $this->plan->backlog
            ->when($this->designerFilter !== '', fn (Collection $cards) => $cards->filter(
                fn (Deliverable $deliverable): bool => (string) $deliverable->assignee_id === $this->designerFilter,
            ))
            ->when($term !== '', fn (Collection $cards) => $cards->filter(fn (Deliverable $deliverable): bool => $this->matches($deliverable, $term)))
            ->values();
    }

    /**
     * Ready work that is blocked: listed greyed and never draggable.
     *
     * @return Collection<int, Deliverable>
     */
    #[Computed]
    public function blockedBacklog(): Collection
    {
        if (! $this->canEdit) {
            return collect();
        }

        $term = mb_strtolower(trim($this->search));

        return $this->plan->blockedBacklog
            ->when($term !== '', fn (Collection $cards) => $cards->filter(fn (Deliverable $deliverable): bool => $this->matches($deliverable, $term)))
            ->values();
    }

    /**
     * @return list<User>
     */
    #[Computed]
    public function designers(): array
    {
        return array_map(fn (array $row): User => $row['assignee'], $this->plan->rows);
    }

    public function previousWeek(): void
    {
        $this->shiftWeek(-7);
    }

    public function nextWeek(): void
    {
        $this->shiftWeek(7);
    }

    public function thisWeek(): void
    {
        $this->weekStart = $this->mondayOf(null)->toDateString();
        $this->resetFeedback();
    }

    /**
     * Drops a card on a designer and a day. A full day asks the manager to confirm an override.
     */
    public function planDeliverable(int $deliverableId, int $assigneeId, string $date, bool $overrideWip = false): void
    {
        $this->resetFeedback();
        $deliverable = Deliverable::query()->findOrFail($deliverableId);
        $assignee = User::query()->findOrFail($assigneeId);
        $day = CarbonImmutable::parse($date);
        $viewer = auth()->user();

        Gate::forUser($viewer)->authorize('plan', $deliverable);
        abort_unless($this->canEdit, 403);

        try {
            app(PlanDeliverable::class)($deliverable, $assignee, $day, $viewer, $overrideWip);
        } catch (WorkflowException $exception) {
            $this->refuse($exception, $deliverable, $assignee, $day);

            return;
        }

        $this->forgetData();
    }

    public function confirmOverride(): void
    {
        $pending = $this->pendingOverride;
        $this->pendingOverride = null;

        if ($pending !== null) {
            $this->planDeliverable($pending['deliverableId'], $pending['assigneeId'], $pending['date'], true);
        }
    }

    public function cancelOverride(): void
    {
        $this->pendingOverride = null;
    }

    public function unplanDeliverable(int $deliverableId): void
    {
        $this->resetFeedback();
        $deliverable = Deliverable::query()->findOrFail($deliverableId);

        try {
            app(UnplanDeliverable::class)($deliverable, auth()->user());
        } catch (WorkflowException $exception) {
            $this->notice = $exception->getMessage();

            return;
        }

        $this->forgetData();
    }

    /**
     * The panel changed a deliverable: reload the grid and the backlog.
     */
    #[On('deliverable-changed')]
    public function refreshAfterPanelChange(): void
    {
        $this->forgetData();
    }

    public function dismissNotice(): void
    {
        $this->notice = '';
    }

    public function render()
    {
        return view('livewire.plan-week.plan-week-page', [
            'weekStartDate' => $this->mondayOf($this->weekStart),
            'isCurrentWeek' => $this->mondayOf($this->weekStart)->equalTo($this->mondayOf(null)),
        ])->title(__('Plan week'));
    }

    private function refuse(WorkflowException $exception, Deliverable $deliverable, User $assignee, CarbonInterface $day): void
    {
        $wip = app(WipPolicy::class);
        $wipIsTheReason = ! $deliverable->isBlocked() && ! $wip->hasRoomToPlan($assignee, $day, $deliverable);

        if ($wipIsTheReason && $this->canEdit) {
            $this->pendingOverride = [
                'deliverableId' => $deliverable->getKey(),
                'assigneeId' => $assignee->getKey(),
                'date' => $day->toDateString(),
                'message' => $exception->getMessage(),
            ];

            return;
        }

        $this->notice = $exception->getMessage();
    }

    private function shiftWeek(int $days): void
    {
        $this->weekStart = $this->mondayOf($this->weekStart)->addDays($days)->toDateString();
        $this->resetFeedback();
    }

    private function resetFeedback(): void
    {
        $this->notice = '';
        $this->pendingOverride = null;
    }

    private function forgetData(): void
    {
        unset($this->plan, $this->rows, $this->backlog, $this->blockedBacklog);
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

    private function matches(Deliverable $deliverable, string $term): bool
    {
        $haystack = mb_strtolower(implode(' ', [
            $deliverable->workOrder?->number,
            $deliverable->wo_number,
            $deliverable->company_name,
            $deliverable->workOrder?->client?->name,
            $deliverable->task_name,
        ]));

        return str_contains($haystack, $term);
    }
}
