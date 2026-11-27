<?php

namespace App\Livewire\Today;

use App\Application\Delivery\Actions\ResolveAlert;
use App\Application\Delivery\Queries\TodayQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\Roles;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * What needs a manager's attention across the shop, plus work in progress per designer.
 */
class ManagerSection extends Component
{
    public function mount(): void
    {
        abort_unless(Roles::isManager(auth()->user()), 403);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function data(): array
    {
        return app(TodayQuery::class)->forManager();
    }

    /**
     * @return Collection<int, array{designer: User, used: int, limit: int}>
     */
    #[Computed]
    public function wip(): Collection
    {
        $used = Deliverable::query()->inWorkspace()->inStatus(DeliverableStatus::IN_DESIGN)->notBlocked()
            ->whereNotNull('assignee_id')->selectRaw('assignee_id, count(*) as used')->groupBy('assignee_id')->pluck('used', 'assignee_id');

        return User::query()->designTeam()->orderBy('name')->get()
            ->map(fn (User $designer): array => ['designer' => $designer, 'used' => (int) ($used[$designer->id] ?? 0), 'limit' => (int) $designer->wip_limit]);
    }

    public function logFollowUp(int $alertId): void
    {
        app(ResolveAlert::class)(Alert::query()->open()->findOrFail($alertId), auth()->user());
        unset($this->data);
    }

    /**
     * The panel changed a deliverable: reload this section only.
     */
    #[On('deliverable-changed')]
    public function refreshAfterPanelChange(): void
    {
        unset($this->data, $this->wip);
    }

    public function render()
    {
        return view('livewire.today.manager-section');
    }
}
