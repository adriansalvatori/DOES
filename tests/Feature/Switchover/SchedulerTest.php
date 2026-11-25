<?php

namespace Tests\Feature\Switchover;

use App\Support\TrelloCutover;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, Event>
     */
    private function events(): array
    {
        return app(Schedule::class)->events();
    }

    private function eventFor(string $command): ?Event
    {
        foreach ($this->events() as $event) {
            if (str_ends_with((string) $event->command, ' '.$command) || str_contains((string) $event->command, "artisan' {$command}")) {
                return $event;
            }
        }

        return null;
    }

    public function test_the_delivery_daily_cycle_runs_at_seven_and_at_half_past_two_without_notifications(): void
    {
        $morning = $this->eventFor('deliverables:daily-cycle');
        $afternoon = $this->eventFor('deliverables:daily-cycle --no-notify');

        $this->assertNotNull($morning);
        $this->assertSame('0 7 * * *', $morning->expression);
        $this->assertNotNull($afternoon);
        $this->assertSame('30 14 * * *', $afternoon->expression);
    }

    public function test_the_legacy_daily_automations_are_no_longer_scheduled(): void
    {
        $this->assertNull($this->eventFor('orders:run-daily-automations'));
        $this->assertNotNull($this->eventFor('db:backup'));
    }

    public function test_trello_sync_is_scheduled_every_fifteen_minutes_only_until_the_cutover(): void
    {
        $sync = $this->eventFor('trello:sync');

        $this->assertNotNull($sync);
        $this->assertSame('*/15 * * * *', $sync->expression);
        $this->assertTrue($sync->filtersPass($this->app));

        TrelloCutover::markComplete();

        $this->assertFalse($sync->filtersPass($this->app));
    }
}
