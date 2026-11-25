<?php

namespace Tests\Unit\Domain;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Scheduling\BusinessCalendar;
use App\Domain\Scheduling\FollowUpPolicy;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FollowUpPolicyTest extends TestCase
{
    private FollowUpPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new FollowUpPolicy(new BusinessCalendar);
    }

    public function test_follow_ups_are_due_on_business_days_three_six_and_nine(): void
    {
        $sentAt = Carbon::parse('2026-09-30 10:00:00');

        $this->assertSame('2026-10-05', $this->policy->dueOn(AlertKind::CLIENT_FOLLOW_UP_1, $sentAt)->toDateString());
        $this->assertSame('2026-10-08', $this->policy->dueOn(AlertKind::CLIENT_FOLLOW_UP_2, $sentAt)->toDateString());
        $this->assertSame('2026-10-13', $this->policy->dueOn(AlertKind::CLIENT_FOLLOW_UP_3, $sentAt)->toDateString());
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function elapsedCases(): array
    {
        return [
            'day 2 has nothing yet' => ['2026-10-02', []],
            'day 3' => ['2026-10-05', ['client_follow_up_1']],
            'day 5' => ['2026-10-07', ['client_follow_up_1']],
            'day 6' => ['2026-10-08', ['client_follow_up_1', 'client_follow_up_2']],
            'day 9 includes the third' => ['2026-10-13', ['client_follow_up_1', 'client_follow_up_2', 'client_follow_up_3']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('elapsedCases')]
    public function test_due_kinds_grow_with_the_elapsed_business_days(string $now, array $expected): void
    {
        $kinds = $this->policy->dueKinds(Carbon::parse('2026-09-30'), Carbon::parse($now));

        $this->assertSame($expected, array_map(fn (AlertKind $kind): string => $kind->value, $kinds));
    }

    public function test_the_client_goes_on_hold_only_after_the_ninth_business_day(): void
    {
        $sentAt = Carbon::parse('2026-09-30');

        $this->assertFalse($this->policy->shouldGoOnHold($sentAt, Carbon::parse('2026-10-13')));
        $this->assertTrue($this->policy->shouldGoOnHold($sentAt, Carbon::parse('2026-10-14')));
    }
}
