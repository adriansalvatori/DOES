<?php

namespace App\Domain\Scheduling;

use App\Domain\Delivery\Enums\AlertKind;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Follow-ups to a client that has not answered a proof: business days 3, 6 and 9, on hold after the ninth.
 */
class FollowUpPolicy
{
    public const HOLD_AFTER_DAYS = 9;

    public function __construct(private BusinessCalendar $calendar) {}

    /**
     * @return array<string, int> Alert kind value => business days after the proof was sent.
     */
    public function schedule(): array
    {
        return [
            AlertKind::CLIENT_FOLLOW_UP_1->value => 3,
            AlertKind::CLIENT_FOLLOW_UP_2->value => 6,
            AlertKind::CLIENT_FOLLOW_UP_3->value => 9,
        ];
    }

    /**
     * @return list<AlertKind>
     */
    public function kinds(): array
    {
        return array_map(fn (string $value): AlertKind => AlertKind::from($value), array_keys($this->schedule()));
    }

    public function dueOn(AlertKind $kind, CarbonInterface $sentAt): CarbonImmutable
    {
        return $this->calendar->addBusinessDays($sentAt, $this->schedule()[$kind->value]);
    }

    public function elapsedBusinessDays(CarbonInterface $sentAt, ?CarbonInterface $now = null): int
    {
        return $this->calendar->businessDaysBetween($sentAt, $now ?? now());
    }

    /**
     * Follow-ups that should exist by now.
     *
     * @return list<AlertKind>
     */
    public function dueKinds(CarbonInterface $sentAt, ?CarbonInterface $now = null): array
    {
        $elapsed = $this->elapsedBusinessDays($sentAt, $now);

        return array_values(array_filter(
            $this->kinds(),
            fn (AlertKind $kind): bool => $elapsed >= $this->schedule()[$kind->value],
        ));
    }

    public function shouldGoOnHold(CarbonInterface $sentAt, ?CarbonInterface $now = null): bool
    {
        return $this->elapsedBusinessDays($sentAt, $now) > self::HOLD_AFTER_DAYS;
    }
}
