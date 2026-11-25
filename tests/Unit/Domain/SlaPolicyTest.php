<?php

namespace Tests\Unit\Domain;

use App\Domain\Scheduling\BusinessCalendar;
use App\Domain\Scheduling\SlaPolicy;
use App\Domain\Scheduling\SlaState;
use Carbon\Carbon;
use Tests\TestCase;

class SlaPolicyTest extends TestCase
{
    private SlaPolicy $sla;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00'));
        $this->sla = new SlaPolicy(new BusinessCalendar);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_work_is_due_in_three_business_days(): void
    {
        $this->assertSame('2026-10-05', $this->sla->newWorkDueTarget()->toDateString());
        $this->assertSame('2026-10-07', $this->sla->newWorkDueTarget(Carbon::parse('2026-10-02'))->toDateString());
    }

    public function test_new_work_created_on_a_weekend_counts_from_monday(): void
    {
        $this->assertSame('2026-10-08', $this->sla->newWorkDueTarget(Carbon::parse('2026-10-03'))->toDateString());
        $this->assertSame('2026-10-08', $this->sla->newWorkDueTarget(Carbon::parse('2026-10-04'))->toDateString());
    }

    public function test_client_changes_and_resume_get_two_business_days(): void
    {
        $this->assertSame('2026-10-02', $this->sla->clientChangesDueTarget()->toDateString());
        $this->assertSame('2026-10-02', $this->sla->resumeDueTarget()->toDateString());
        $this->assertSame('2026-10-06', $this->sla->resumeDueTarget(Carbon::parse('2026-10-02'))->toDateString());
    }

    public function test_approval_is_due_the_next_business_day_or_today_when_urgent(): void
    {
        $this->assertSame('2026-10-01', $this->sla->approvalDueTarget(false)->toDateString());
        $this->assertSame('2026-09-30', $this->sla->approvalDueTarget(true)->toDateString());
        $this->assertSame('2026-10-05', $this->sla->approvalDueTarget(false, Carbon::parse('2026-10-02'))->toDateString());
    }

    public function test_state_is_on_track_before_the_due_day(): void
    {
        $this->assertSame(SlaState::ON_TRACK, $this->sla->state(Carbon::parse('2026-10-01')));
        $this->assertSame(SlaState::ON_TRACK, $this->sla->state(null));
    }

    public function test_state_is_almost_overdue_on_the_due_day_before_16_00(): void
    {
        $this->assertSame(SlaState::ALMOST_OVERDUE, $this->sla->state(Carbon::parse('2026-09-30')));
        $this->assertSame(SlaState::ALMOST_OVERDUE, $this->sla->state(Carbon::parse('2026-09-30'), Carbon::parse('2026-09-30 15:59:00')));
    }

    public function test_state_is_overdue_from_16_00_on_the_due_day(): void
    {
        $this->assertSame(SlaState::OVERDUE, $this->sla->state(Carbon::parse('2026-09-30'), Carbon::parse('2026-09-30 16:00:00')));
    }

    public function test_state_is_overdue_after_the_due_day(): void
    {
        $this->assertSame(SlaState::OVERDUE, $this->sla->state(Carbon::parse('2026-09-29')));
    }
}
