<?php

namespace App\Livewire\Analytics;

use App\Application\Reporting\ReportPeriod;
use App\Application\Reporting\ReportRecipients;
use App\Livewire\Analytics\Support\AnalyticsMetrics;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Flow metrics for coordinators and the owner: cycle time per column, weekly throughput, WIP against its limit,
 * client wait and the closed-work KPIs, plus the weekly and biweekly PDF reports. Designers do not see it.
 */
class AnalyticsPage extends Component
{
    public const WEEKLY_CHOICES = 12;

    public const BIWEEKLY_CHOICES = 6;

    public string $weeklyStart = '';

    public string $biweeklyStart = '';

    public string $recipients = '';

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && ! $user->isDesigner(), 403);

        $this->weeklyStart = ReportPeriod::lastCompleted(ReportPeriod::WEEKLY, now())->start->toDateString();
        $this->biweeklyStart = ReportPeriod::lastCompleted(ReportPeriod::BIWEEKLY, now())->start->toDateString();
        $this->recipients = implode(', ', app(ReportRecipients::class)->saved());
    }

    /**
     * Finished report windows to pick from, newest first.
     *
     * @return array{weekly: list<ReportPeriod>, biweekly: list<ReportPeriod>}
     */
    #[Computed]
    public function reportPeriods(): array
    {
        return [
            'weekly' => ReportPeriod::recent(ReportPeriod::WEEKLY, now(), self::WEEKLY_CHOICES),
            'biweekly' => ReportPeriod::recent(ReportPeriod::BIWEEKLY, now(), self::BIWEEKLY_CHOICES),
        ];
    }

    /**
     * Saves who gets the emailed reports. Empty means every active admin.
     */
    public function saveRecipients(): void
    {
        $emails = ReportRecipients::parse($this->recipients);

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->addError('recipients', __(':email is not a valid email address.', ['email' => $email]));

                return;
            }
        }

        app(ReportRecipients::class)->save($emails);
        $this->recipients = implode(', ', $emails);
        $this->resetErrorBag('recipients');
        $this->dispatch('toast', message: __('Report recipients saved.'));
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
            'reportPeriods' => $this->reportPeriods,
        ])->layout('components.layouts.app', ['title' => __('Analytics').' - '.config('app.name')]);
    }
}
