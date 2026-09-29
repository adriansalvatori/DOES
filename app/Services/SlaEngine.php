<?php

namespace App\Services;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\OrderEvent;
use Carbon\Carbon;

class SlaEngine
{
    public const DESIGN_BASE_SLA_DAYS = 3;

    public const CLIENT_CHANGES_SLA_DAYS = 2;

    public const MISSING_MEASURES_SLA_DAYS = 2;

    public const CLIENT_FOLLOWUP_INTERVAL_DAYS = 3;

    public const ON_HOLD_NO_RESPONSE_DAYS = 9;

    /**
     * Calculate initial due date for an order based on its status.
     */
    public function calculateDueDate(CoreStatus $status, ?Carbon $startDate = null): Carbon
    {
        $startDate = $startDate ? $startDate->copy() : Carbon::now();

        // Ensure start date is on a business day if created on a weekend
        if ($startDate->isSaturday()) {
            $startDate->addDays(2);
        } elseif ($startDate->isSunday()) {
            $startDate->addDays(1);
        }

        if (CoreStatus::isPendingDesign($status)) {
            return $startDate->addWeekdays(self::DESIGN_BASE_SLA_DAYS);
        }

        return match ($status) {
            CoreStatus::ENTRANTE => $startDate->addWeekdays(self::MISSING_MEASURES_SLA_DAYS),
            default => $startDate->addWeekdays(self::DESIGN_BASE_SLA_DAYS),
        };
    }

    /**
     * Update current due date with historical auditing.
     */
    public function updateDueDate(
        Order $order,
        Carbon $newDueDate,
        string $reason,
        ?string $triggerEvent = null,
        ?Carbon $clientPromisedDate = null,
        string $createdBy = 'system'
    ): void {
        $previousDueDate = $order->current_due_date;

        DueDateHistory::create([
            'order_id' => $order->id,
            'previous_due_date' => $previousDueDate ? $previousDueDate->toDateString() : null,
            'new_due_date' => $newDueDate->toDateString(),
            'reason' => $reason,
            'trigger_event' => $triggerEvent,
            'created_by' => $createdBy,
            'client_promised_date' => $clientPromisedDate ? $clientPromisedDate->toDateString() : null,
        ]);

        $order->update([
            'current_due_date' => $newDueDate,
            'original_due_date' => $order->original_due_date ?? $newDueDate,
            'last_meaningful_update' => now(),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'DUE_DATE_CHANGED',
            'actor' => $createdBy,
            'previous_value' => $previousDueDate ? $previousDueDate->toDateString() : 'N/A',
            'new_value' => $newDueDate->toDateString(),
            'metadata' => [
                'reason' => $reason,
                'client_promised_date' => $clientPromisedDate ? $clientPromisedDate->toDateString() : null,
            ],
        ]);
    }

    /**
     * Check and evaluate overdue / almost overdue state for an order.
     */
    public function checkOverdue(Order $order): bool
    {
        $isExcludedStatus = $order->isPaused() ||
            $order->done_today ||
            in_array($order->core_status, [
                CoreStatus::ENVIADO_AL_CLIENTE,
                CoreStatus::EN_PRODUCCION,
                CoreStatus::ON_HOLD,
                CoreStatus::ARCHIVED,
            ], true);

        if ($isExcludedStatus) {
            if ($order->hasFlag(Substatus::OVERDUE) || $order->hasFlag(Substatus::ALMOST_OVERDUE) || $order->substatus === Substatus::OVERDUE || $order->substatus === Substatus::ALMOST_OVERDUE) {
                $order->removeFlag(Substatus::OVERDUE);
                $order->removeFlag(Substatus::ALMOST_OVERDUE);

                if ($order->substatus === Substatus::OVERDUE || $order->substatus === Substatus::ALMOST_OVERDUE) {
                    $targetSubstatus = match ($order->core_status) {
                        CoreStatus::ENVIADO_AL_CLIENTE => Substatus::WAITING_FOR_CLIENT,
                        CoreStatus::EN_PRODUCCION => Substatus::ENVIADO_EN_ALTA,
                        default => null,
                    };
                    $order->substatus = $targetSubstatus;
                }
                $order->save();
            }

            return false;
        }

        if (! $order->current_due_date) {
            if ($order->hasFlag(Substatus::OVERDUE) || $order->hasFlag(Substatus::ALMOST_OVERDUE) || $order->substatus === Substatus::OVERDUE || $order->substatus === Substatus::ALMOST_OVERDUE) {
                $order->removeFlag(Substatus::OVERDUE);
                $order->removeFlag(Substatus::ALMOST_OVERDUE);

                if ($order->substatus === Substatus::OVERDUE || $order->substatus === Substatus::ALMOST_OVERDUE) {
                    $order->substatus = null;
                }
                $order->save();
            }

            app(AutomationEngine::class)->dismissPendingOverdueTasks($order);

            return false;
        }

        $now = now();
        $isPastTwoThirty = ($now->hour > 14 || ($now->hour === 14 && $now->minute >= 30));

        if ($order->isOverdue()) {
            if (! $order->hasFlag(Substatus::OVERDUE)) {
                $order->addFlag(Substatus::OVERDUE);
                $order->removeFlag(Substatus::ALMOST_OVERDUE);
                if ($order->substatus === Substatus::OVERDUE) {
                    $order->substatus = null;
                }
                $order->save();
            }

            if ($isPastTwoThirty) {
                app(AutomationEngine::class)->checkAndCreateOverdueTask($order);
            }

            return true;
        }

        if ($order->isDueToday()) {
            if (! $order->hasFlag(Substatus::ALMOST_OVERDUE)) {
                $order->addFlag(Substatus::ALMOST_OVERDUE);
                $order->removeFlag(Substatus::OVERDUE);
                if ($order->substatus === Substatus::ALMOST_OVERDUE) {
                    $order->substatus = null;
                }
                $order->save();
            }

            if ($isPastTwoThirty) {
                app(AutomationEngine::class)->checkAndCreateOverdueTask($order);
            }

            return true;
        }

        // If not overdue nor due today, clear SLA flags
        if ($order->hasFlag(Substatus::OVERDUE) || $order->hasFlag(Substatus::ALMOST_OVERDUE)) {
            $order->removeFlag(Substatus::OVERDUE);
            $order->removeFlag(Substatus::ALMOST_OVERDUE);
            $order->save();
        }

        return false;
    }
}
