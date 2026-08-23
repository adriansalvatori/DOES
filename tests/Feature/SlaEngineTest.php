<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Services\SlaEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_due_date_skips_weekends_when_adding_sla_days(): void
    {
        $slaEngine = new SlaEngine;

        // 2026-08-21 is a Friday.
        $friday = Carbon::parse('2026-08-21');

        // 3-weekday SLA starting Friday:
        // Friday (day 0) -> Monday (day 1) -> Tuesday (day 2) -> Wednesday (day 3 = 2026-08-26)
        $dueDate = $slaEngine->calculateDueDate(CoreStatus::EURALIZ_ORDERS_RECEIVED, $friday);

        $this->assertEquals('2026-08-26', $dueDate->toDateString());
        $this->assertEquals('Wednesday', $dueDate->format('l'));
    }

    public function test_calculate_due_date_from_thursday_skips_weekend(): void
    {
        $slaEngine = new SlaEngine;

        // 2026-08-20 is a Thursday.
        $thursday = Carbon::parse('2026-08-20');

        // 3-weekday SLA starting Thursday:
        // Friday (day 1) -> Monday (day 2) -> Tuesday (day 3 = 2026-08-25)
        $dueDate = $slaEngine->calculateDueDate(CoreStatus::EURALIZ_ORDERS_RECEIVED, $thursday);

        $this->assertEquals('2026-08-25', $dueDate->toDateString());
        $this->assertEquals('Tuesday', $dueDate->format('l'));
    }

    public function test_calculate_due_date_from_weekend_rolls_to_business_days(): void
    {
        $slaEngine = new SlaEngine;

        // 2026-08-22 is a Saturday.
        $saturday = Carbon::parse('2026-08-22');

        // 2-weekday SLA for ENTRANTE starting on Saturday:
        // Saturday rolls to Monday (start) -> Tuesday (day 1) -> Wednesday (day 2 = 2026-08-26)
        $dueDate = $slaEngine->calculateDueDate(CoreStatus::ENTRANTE, $saturday);

        $this->assertEquals('2026-08-26', $dueDate->toDateString());
        $this->assertEquals('Wednesday', $dueDate->format('l'));
    }
}
