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

        return match ($status) {
            CoreStatus::EURALIZ_ORDERS_RECEIVED,
            CoreStatus::ADRIAN_ORDERS_RECEIVED,
            CoreStatus::CESAR_ORDERS_RECEIVED => $startDate->addWeekdays(self::DESIGN_BASE_SLA_DAYS),

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
        if ($order->isPaused() || $order->core_status === CoreStatus::EN_PRODUCCION || $order->core_status === CoreStatus::ENVIADO_AL_CLIENTE || $order->done_today) {
            return false;
        }

        if (! $order->current_due_date) {
            if ($order->substatus === Substatus::OVERDUE || $order->substatus === Substatus::ALMOST_OVERDUE) {
                $previousSubstatus = $order->substatus ? $order->substatus->value : null;
                $order->update(['substatus' => null]);

                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'SUBSTATUS_CHANGED',
                    'actor' => 'SlaEngine',
                    'previous_value' => $previousSubstatus,
                    'new_value' => null,
                    'metadata' => ['reason' => 'Due date removed/cleared'],
                ]);
            }

            app(AutomationEngine::class)->dismissPendingOverdueTasks($order);

            return false;
        }

        $now = now();
        $isPastTwoThirty = ($now->hour > 14 || ($now->hour === 14 && $now->minute >= 30));

        if ($order->isOverdue()) {
            if ($order->substatus !== Substatus::OVERDUE) {
                $order->update(['substatus' => Substatus::OVERDUE]);

                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'SUBSTATUS_CHANGED',
                    'actor' => 'SlaEngine',
                    'previous_value' => $order->substatus ? $order->substatus->value : null,
                    'new_value' => Substatus::OVERDUE->value,
                    'metadata' => ['reason' => 'Current due date exceeded or past 4:00 PM on due date'],
                ]);
            }

            if ($isPastTwoThirty) {
                app(AutomationEngine::class)->checkAndCreateOverdueTask($order);
            }

            return true;
        }

        if ($order->isDueToday()) {
            if ($order->substatus !== Substatus::ALMOST_OVERDUE) {
                $order->update(['substatus' => Substatus::ALMOST_OVERDUE]);

                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'SUBSTATUS_CHANGED',
                    'actor' => 'SlaEngine',
                    'previous_value' => $order->substatus ? $order->substatus->value : null,
                    'new_value' => Substatus::ALMOST_OVERDUE->value,
                    'metadata' => ['reason' => 'Order is due today'],
                ]);
            }

            if ($isPastTwoThirty) {
                app(AutomationEngine::class)->checkAndCreateOverdueTask($order);
            }
        }

        return false;
    }
}
