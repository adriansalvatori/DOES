<?php

namespace App\Livewire\Analytics;

use App\Enums\UserRole;
use App\Livewire\Analytics\Support\AnalyticsMetrics;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Flow metrics for coordinators and the owner: cycle time per column, weekly throughput, WIP against its limit,
 * client wait and the closed-work KPIs. Designers do not see it.
 */
class AnalyticsPage extends Component
{
    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role !== UserRole::DESIGNER, 403);
    }

    #[Computed]
    public function metrics(): AnalyticsMetrics
    {
        return new AnalyticsMetrics;
    }

    public function render()
    {
        return view('livewire.analytics-page.analytics-page', [
            'cycleTimes' => $this->metrics->cycleTimePerColumn(),
            'clientWait' => $this->metrics->clientWait(),
            'throughput' => $this->metrics->weeklyThroughput(),
            'wip' => $this->metrics->wipPerDesigner(),
            'closed' => $this->metrics->closedKpis(),
            'windowDays' => AnalyticsMetrics::WINDOW_DAYS,
        ])->layout('components.layouts.app', ['title' => __('Analytics').' - '.config('app.name')]);
    }
}
