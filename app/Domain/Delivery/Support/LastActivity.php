<?php

namespace App\Domain\Delivery\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * When a person last did something on a deliverable: a logged action by a person, a completed task or a comment.
 * Automation, SLA and daily-cycle entries do not count, so a card nobody touches keeps ageing.
 * When nothing counts, the deliverable's creation is its last activity.
 */
class LastActivity
{
    /**
     * Actors the legacy engines and the domain log write for work done without a person.
     */
    public const SYSTEM_ACTORS = ['AutomationEngine', 'SlaEngine', 'Sistema', 'system', 'import'];

    private const CHUNK = 500;

    /**
     * @param  list<int>  $deliverableIds
     * @return array<int, CarbonImmutable> Last activity by deliverable id; ids that do not exist are left out.
     */
    public function forDeliverables(array $deliverableIds): array
    {
        $latest = [];

        foreach (array_chunk(array_values(array_unique($deliverableIds)), self::CHUNK) as $ids) {
            $sources = [
                DB::table('orders')->whereIn('id', $ids)->pluck('created_at', 'id'),
                DB::table('order_events')->whereIn('order_id', $ids)
                    ->whereNotNull('actor')->whereNotIn('actor', self::SYSTEM_ACTORS)
                    ->groupBy('order_id')->selectRaw('order_id, MAX(created_at) as at')->pluck('at', 'order_id'),
                DB::table('related_tasks')->whereIn('order_id', $ids)->whereNotNull('completed_at')
                    ->groupBy('order_id')->selectRaw('order_id, MAX(completed_at) as at')->pluck('at', 'order_id'),
                DB::table('order_comments')->whereIn('order_id', $ids)
                    ->groupBy('order_id')->selectRaw('order_id, MAX(created_at) as at')->pluck('at', 'order_id'),
            ];

            foreach ($sources as $source) {
                foreach ($source as $id => $at) {
                    if ($at === null) {
                        continue;
                    }
                    $moment = CarbonImmutable::parse($at);
                    if (! isset($latest[$id]) || $moment->greaterThan($latest[$id])) {
                        $latest[$id] = $moment;
                    }
                }
            }
        }

        return $latest;
    }
}
