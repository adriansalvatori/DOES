<?php

namespace App\Livewire\Today;

use App\Domain\Delivery\Support\Roles;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One Today screen with a section per role the viewer holds: designer, reviewer, manager, customer service.
 * Managers can switch to a designer's Today with the team chips.
 */
#[Layout('components.layouts.app')]
class TodayPage extends Component
{
    #[Url(as: 'designer')]
    public ?int $viewAs = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        if ($this->viewAs !== null && ! ($this->isManager && $this->designers->contains('id', $this->viewAs))) {
            $this->viewAs = null;
        }
    }

    #[Computed]
    public function isManager(): bool
    {
        return Roles::isManager(auth()->user());
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function designers(): Collection
    {
        return User::query()->designTeam()->orderBy('name')->get();
    }

    /**
     * Section names to render, in order.
     *
     * @return list<string>
     */
    #[Computed]
    public function sections(): array
    {
        $viewer = auth()->user();

        if ($this->viewAs !== null) {
            return ['designer'];
        }

        $sections = [];
        if ($viewer->isDesigner()) {
            $sections[] = 'designer';
        }
        if ($viewer->is_reviewer) {
            $sections[] = 'reviewer';
        }
        if ($this->isManager) {
            $sections[] = 'manager';
        }
        if ($viewer->isCustomerService()) {
            $sections[] = 'customer_service';
        }

        return $sections;
    }

    public function viewDesigner(?int $designerId): void
    {
        $this->viewAs = $this->isManager && $this->designers->contains('id', $designerId) ? $designerId : null;
        unset($this->sections);
    }

    public function render()
    {
        return view('livewire.today.today-page', [
            'today' => now(),
            'designerId' => $this->viewAs ?? auth()->id(),
        ]);
    }
}
