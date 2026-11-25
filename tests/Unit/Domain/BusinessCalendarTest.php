<?php

namespace Tests\Unit\Domain;

use App\Domain\Scheduling\BusinessCalendar;
use Carbon\Carbon;
use Tests\TestCase;

class BusinessCalendarTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_adding_business_days_skips_weekends(): void
    {
        $calendar = new BusinessCalendar;

        $this->assertSame('2026-10-05', $calendar->addBusinessDays(Carbon::parse('2026-09-30'), 3)->toDateString());
        $this->assertSame('2026-10-07', $calendar->addBusinessDays(Carbon::parse('2026-10-02'), 3)->toDateString());
        $this->assertSame('2026-09-30', $calendar->addBusinessDays(Carbon::parse('2026-09-30'), 0)->toDateString());
    }

    public function test_a_weekend_start_counts_the_next_business_day_as_the_first(): void
    {
        $calendar = new BusinessCalendar;

        $this->assertSame('2026-10-05', $calendar->addBusinessDays(Carbon::parse('2026-10-03'), 1)->toDateString());
        $this->assertSame('2026-10-06', $calendar->addBusinessDays(Carbon::parse('2026-10-03'), 2)->toDateString());
        $this->assertSame('2026-10-05', $calendar->rollForward(Carbon::parse('2026-10-04'))->toDateString());
        $this->assertSame('2026-09-30', $calendar->rollForward(Carbon::parse('2026-09-30'))->toDateString());
    }

    public function test_registered_holidays_are_not_business_days(): void
    {
        $calendar = new BusinessCalendar(['2026-10-01']);

        $this->assertTrue($calendar->isHoliday(Carbon::parse('2026-10-01')));
        $this->assertFalse($calendar->isBusinessDay(Carbon::parse('2026-10-01')));
        $this->assertSame('2026-10-02', $calendar->nextBusinessDay(Carbon::parse('2026-09-30'))->toDateString());
    }

    public function test_business_days_between_counts_each_business_day_after_the_start(): void
    {
        $calendar = new BusinessCalendar;

        $this->assertSame(3, $calendar->businessDaysBetween(Carbon::parse('2026-09-28'), Carbon::parse('2026-10-01')));
        $this->assertSame(1, $calendar->businessDaysBetween(Carbon::parse('2026-10-02'), Carbon::parse('2026-10-05')));
        $this->assertSame(0, $calendar->businessDaysBetween(Carbon::parse('2026-10-02'), Carbon::parse('2026-10-04')));
        $this->assertSame(9, $calendar->businessDaysBetween(Carbon::parse('2026-09-30'), Carbon::parse('2026-10-13')));
    }

    public function test_welcome_email_is_due_the_same_business_day_before_the_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));
        $calendar = new BusinessCalendar;

        $this->assertSame('2026-09-30', $calendar->welcomeEmailDate()->toDateString());
        $this->assertSame('2026-09-30', $calendar->welcomeEmailDate(Carbon::parse('2026-09-30 16:29:00'))->toDateString());
    }

    public function test_welcome_email_moves_to_the_next_business_day_after_16_30_or_on_a_weekend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 17:00:00'));
        $calendar = new BusinessCalendar;

        $this->assertSame('2026-10-01', $calendar->welcomeEmailDate()->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-09-30 16:30:00'));
        $this->assertSame('2026-10-01', $calendar->welcomeEmailDate()->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-10-03 11:00:00'));
        $this->assertSame('2026-10-05', $calendar->welcomeEmailDate()->toDateString());
    }

    public function test_a_welcome_email_for_an_entry_in_the_past_counts_from_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));

        $this->assertSame('2026-09-30', (new BusinessCalendar)->welcomeEmailDate(Carbon::parse('2026-09-25 09:00:00'))->toDateString());
    }
}
