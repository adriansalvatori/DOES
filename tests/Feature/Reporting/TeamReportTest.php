<?php

namespace Tests\Feature\Reporting;

use App\Application\Reporting\ReportPeriod;
use App\Application\Reporting\ReportRecipients;
use App\Application\Reporting\TeamReportQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\Priority;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\Task;
use App\Livewire\Analytics\AnalyticsPage;
use App\Mail\TeamReportMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\Domain\DeliveryTestCase;

class TeamReportTest extends DeliveryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAt('2026-11-24 09:00:00');
    }

    private function event(Deliverable $deliverable, ?string $from, string $to, string $at): void
    {
        DB::table('order_events')->insert([
            'order_id' => $deliverable->id, 'event_type' => 'deliverable_moved', 'actor' => 'Someone',
            'previous_value' => $from, 'new_value' => $to, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function approve(Deliverable $deliverable, string $at, string $source = 'client'): void
    {
        DB::table('approvals')->insert([
            'deliverable_id' => $deliverable->id, 'source' => $source, 'measurements_confirmed' => false, 'estimate_approved' => false,
            'approved_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    public function test_weekly_and_biweekly_windows(): void
    {
        $now = CarbonImmutable::parse('2026-11-24 09:00:00');

        $weekly = ReportPeriod::lastCompleted(ReportPeriod::WEEKLY, $now);
        $this->assertSame('2026-11-16', $weekly->start->toDateString());
        $this->assertSame('2026-11-22', $weekly->lastDay()->toDateString());
        $this->assertSame('2026-11-09', $weekly->previous()->start->toDateString());

        $biweekly = ReportPeriod::lastCompleted(ReportPeriod::BIWEEKLY, $now);
        $this->assertSame('2026-11-09', $biweekly->start->toDateString(), 'Biweekly windows start every second Monday from Jan 5.');
        $this->assertSame('2026-11-22', $biweekly->lastDay()->toDateString());
        $this->assertSame(['2026-11-09', '2026-11-16'], array_map(fn ($week) => $week->toDateString(), $biweekly->weekStarts()));
        $this->assertSame('2026-11-09', ReportPeriod::containing(ReportPeriod::BIWEEKLY, CarbonImmutable::parse('2026-11-20'))->start->toDateString());

        $this->assertTrue(ReportPeriod::startsBiweeklyWindow(CarbonImmutable::parse('2026-11-23')));
        $this->assertFalse(ReportPeriod::startsBiweeklyWindow(CarbonImmutable::parse('2026-11-30')));
        $this->assertFalse(ReportPeriod::startsBiweeklyWindow(CarbonImmutable::parse('2026-11-24')));
        $this->assertCount(6, ReportPeriod::recent(ReportPeriod::BIWEEKLY, $now, 6));
    }

    public function test_the_report_credits_points_proofs_rework_and_open_work_to_each_designer(): void
    {
        $ana = $this->designer('Ana');
        $beto = $this->designer('Beto');

        $approved = $this->deliverable(DeliverableStatus::PRODUCTION_FILES, ['assignee_id' => $ana->id, 'points' => 8]);
        $this->approve($approved, '2026-11-18 10:00:00');
        $this->approve($approved, '2026-11-19 10:00:00', 'manager');
        $this->approve($this->deliverable(DeliverableStatus::PRODUCTION_FILES, ['assignee_id' => $ana->id, 'points' => 3]), '2026-11-12 10:00:00');
        $this->approve($this->deliverable(DeliverableStatus::PRODUCTION_FILES, ['assignee_id' => $beto->id, 'points' => 5]), '2026-11-17 09:00:00', 'reviewer');

        $proofed = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['assignee_id' => $ana->id, 'points' => 2]);
        $this->event($proofed, 'TO DO TODAY', 'ENVIADO AL CLIENTE', '2026-11-05 10:00:00');
        $this->event($proofed, 'in_design', 'internal_review', '2026-11-17 10:00:00');
        $this->event($proofed, 'internal_review', 'in_design', '2026-11-17 15:00:00');
        $this->event($proofed, 'in_design', 'internal_review', '2026-11-17 18:00:00');
        $this->event($proofed, 'TO DO TODAY', 'ENVIADO AL CLIENTE', '2026-11-18 11:00:20');
        $this->event($proofed, 'TO DO TODAY', 'ENVIADO AL CLIENTE', '2026-11-18 11:00:40');
        $this->event($proofed, 'TO DO TODAY', 'ENVIADO AL CLIENTE', '2026-11-20 12:00:30');
        $this->event($proofed, 'in_design', 'client_review', '2026-11-25 11:00:00');

        DB::table('related_tasks')->insert(['order_id' => $proofed->id, 'title' => 'Follow up', 'type' => 'FOLLOW UP CLIENTE', 'status' => 'done', 'points' => 0, 'completed_at' => '2026-11-20 12:00:10', 'created_at' => now(), 'updated_at' => now()]);
        Task::factory()->for($proofed, 'deliverable')->create(['title' => 'Ajustes cliente', 'status' => 'done', 'points' => 3, 'completed_at' => '2026-11-19 09:00:00']);
        Task::factory()->for($proofed, 'deliverable')->create(['title' => 'First Proposal', 'status' => 'done', 'points' => 2, 'completed_at' => '2026-11-12 09:00:00']);
        $this->deliverable(DeliverableStatus::DONE, ['assignee_id' => $beto->id, 'points' => 13, 'close_reason' => 'completed', 'closed_at' => '2026-11-21 10:00:00']);

        $late = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $beto->id, 'due_target' => '2026-11-10', 'priority' => 'highest']);
        Task::factory()->for($late, 'deliverable')->create(['title' => 'Ajustes cliente', 'points' => 5]);
        Task::factory()->for($late, 'deliverable')->create(['title' => 'First Proposal', 'status' => 'done', 'points' => 8, 'completed_at' => '2026-10-01 09:00:00']);
        $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $beto->id, 'points' => 3, 'in_backlog' => true]);
        $held = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $beto->id, 'points' => 8]);
        Hold::factory()->create(['deliverable_id' => $held->id]);

        $report = app(TeamReportQuery::class)->run(ReportPeriod::lastCompleted(ReportPeriod::WEEKLY, now()));
        $row = fn (User $designer) => collect($report->designers)->firstWhere('designer.id', $designer->id);

        $this->assertSame(8, $row($ana)['approved_points'], 'Two approvals of the same card count once; reviewer approvals are internal.');
        $this->assertSame(1, $row($ana)['approved_count']);
        $this->assertSame(3, $row($ana)['previous_approved_points']);
        $this->assertSame(0, $row($beto)['approved_points']);
        $this->assertSame(2, $row($ana)['proofs_to_reviewer']);
        $this->assertSame(1, $row($ana)['proofs_to_client'], 'Repeats within a minute, a return after a follow-up task and moves after the window do not count.');
        $this->assertSame(2, $row($ana)['revisions'], 'The second send to Camila and the send to a client who saw it on Nov 5 are revisions.');
        $this->assertSame(67, $row($ana)['rework_pct']);
        $this->assertSame(2, $row($ana)['tasks_done']);
        $this->assertSame(3, $row($ana)['points_done'], 'Points done are the points of the tasks completed in the window.');
        $this->assertSame(2, $row($ana)['previous_points_done']);
        $this->assertSame(3, $report->team['points_done']);
        $this->assertSame(13, $row($beto)['closed_points']);
        $this->assertSame(5, $row($beto)['open_points'], 'Open points are the points still to do; backlog and held work stay out.');
        $this->assertSame(1, $row($beto)['overdue_count']);
        $this->assertSame(1, $row($beto)['high_priority_open']);
        $this->assertSame(8, $report->team['approved_points']);
        $highest = collect($report->priorityMix)->firstWhere('priority', Priority::HIGHEST);
        $this->assertSame(['count' => 1, 'points' => 5], ['count' => $highest['count'], 'points' => $highest['points']]);
        $this->assertSame(Priority::HIGHEST, $report->attention->first()->priority);
    }

    public function test_managers_download_the_pdf_and_designers_cannot(): void
    {
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $this->designer('Ana')->id, 'points' => 5, 'task_name' => 'Ñandú sign']);

        $response = $this->actingAs($this->manager())->get(route('reports.team', ['kind' => 'biweekly', 'start' => '2026-11-12']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('kudos-biweekly-report-2026-11-09.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs($this->designer('Beto'))->get(route('reports.team', ['kind' => 'weekly']))->assertForbidden();
        $this->actingAs($this->manager())->get('/analytics/reports/monthly')->assertNotFound();
    }

    public function test_the_weekly_report_is_emailed_with_the_pdf_attached(): void
    {
        Mail::fake();
        app(ReportRecipients::class)->save(['owner@kudos.test']);

        $this->artisan('reports:send', ['kind' => 'weekly'])->assertSuccessful();

        Mail::assertSent(TeamReportMail::class, function (TeamReportMail $mail): bool {
            return $mail->hasTo('owner@kudos.test')
                && $mail->period->start->toDateString() === '2026-11-16'
                && str_starts_with($mail->pdf, '%PDF')
                && $mail->hasAttachment(Attachment::fromData(fn () => $mail->pdf, 'kudos-weekly-report-2026-11-16.pdf')->withMime('application/pdf'));
        });
    }

    public function test_the_biweekly_report_goes_out_only_when_a_window_starts(): void
    {
        Mail::fake();
        app(ReportRecipients::class)->save(['owner@kudos.test']);

        $this->artisan('reports:send', ['kind' => 'biweekly'])->assertSuccessful();
        Mail::assertNothingSent();

        $this->freezeAt('2026-11-23 07:45:00');
        $this->artisan('reports:send', ['kind' => 'biweekly'])->assertSuccessful();
        Mail::assertSent(TeamReportMail::class, fn (TeamReportMail $mail): bool => $mail->period->start->toDateString() === '2026-11-09');
    }

    public function test_without_saved_addresses_every_active_admin_gets_it_but_never_example_domains(): void
    {
        User::factory()->admin()->create(['email' => 'boss@kudos.test', 'active' => true]);
        User::factory()->admin()->create(['email' => 'fake@example.net', 'active' => true]);
        User::factory()->admin()->create(['email' => 'gone@kudos.test', 'active' => false]);

        $this->assertSame(['boss@kudos.test'], app(ReportRecipients::class)->emails());
    }

    public function test_analytics_lists_the_periods_and_saves_recipients(): void
    {
        $page = Livewire::actingAs($this->manager())->test(AnalyticsPage::class)
            ->assertSee(__('Reports'))
            ->assertSet('weeklyStart', '2026-11-16')
            ->assertSet('biweeklyStart', '2026-11-09')
            ->assertSeeHtml('data-report-download="weekly"');

        $page->set('recipients', 'owner@kudos.test, not-an-email')->call('saveRecipients')->assertHasErrors('recipients');
        $this->assertSame([], app(ReportRecipients::class)->saved());

        $page->set('recipients', 'Owner@Kudos.test; ops@kudos.test')->call('saveRecipients')->assertHasNoErrors()->assertSet('recipients', 'owner@kudos.test, ops@kudos.test');
        $this->assertSame(['owner@kudos.test', 'ops@kudos.test'], app(ReportRecipients::class)->saved());
    }
}
