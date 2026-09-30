<?php

namespace App\Services\WorkOrder;

use App\Contracts\WorkOrderNumberGenerator;
use App\Models\Order;

class SequentialWorkOrderGenerator implements WorkOrderNumberGenerator
{
    /**
     * Generate the next sequential work order number prefixed with "WO ".
     *
     * @param  array<string, mixed>  $context
     */
    public function generateNext(array $context = []): string
    {
        $lastNumber = $this->getLastNumber();

        if ($lastNumber !== null) {
            $candidate = ((int) $lastNumber) + 1;
        } else {
            $candidate = (int) config('work_orders.starting_number', 10001);
        }

        // Guarantee uniqueness against any existing or soft-deleted order
        while (Order::withTrashed()
            ->where(function ($query) use ($candidate) {
                $query->where('wo_number', "WO {$candidate}")
                    ->orWhere('wo_number', (string) $candidate);
            })
            ->exists()) {
            $candidate++;
        }

        return "WO {$candidate}";
    }

    /**
     * Generate only the numeric digits for the next work order number.
     *
     * @param  array<string, mixed>  $context
     */
    public function generateNextDigits(array $context = []): string
    {
        $full = $this->generateNext($context);

        return trim((string) preg_replace('/^WO\s*/i', '', $full));
    }

    /**
     * Retrieve the last recorded valid work order number.
     */
    public function getLastNumber(): ?string
    {
        $recentOrders = Order::withTrashed()
            ->whereNotNull('wo_number')
            ->where('wo_number', '!=', '')
            ->orderByDesc('id')
            ->take(100)
            ->get();

        $validNumbers = $recentOrders
            ->map(function (Order $order) {
                if ($order->hasNoWo()) {
                    return null;
                }

                if (! preg_match('/(?:WO\s*)?(\d{3,6})\b/i', $order->wo_number, $matches)) {
                    return null;
                }

                $num = (int) $matches[1];

                // Ignore dummy notes / placeholder numbers
                if ($num === 55555 || $num <= 0) {
                    return null;
                }

                // Ignore outlier 6-digit anomalies from historical Trello typos (> 50000)
                if ($num > 50000) {
                    return null;
                }

                return $num;
            })
            ->filter()
            ->values();

        if ($validNumbers->isEmpty()) {
            return null;
        }

        return (string) $validNumbers->max();
    }
}
