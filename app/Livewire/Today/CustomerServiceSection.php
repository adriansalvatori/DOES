<?php

namespace App\Livewire\Today;

use App\Application\Delivery\Data\ResolverGroup;
use App\Application\Delivery\Queries\TodayQuery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Open blocks and holds at a glance, linking to the Resolver where they are cleared.
 */
class CustomerServiceSection extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isCustomerService(), 403);
    }

    /**
     * @return list<ResolverGroup>
     */
    #[Computed]
    public function groups(): array
    {
        return app(TodayQuery::class)->forCustomerService(auth()->user())['groups'];
    }

    /**
     * The panel changed a deliverable: reload this section only.
     */
    #[On('deliverable-changed')]
    public function refreshAfterPanelChange(): void
    {
        unset($this->groups);
    }

    public function render()
    {
        return view('livewire.today.customer-service-section');
    }
}
