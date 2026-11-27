<?php

namespace App\Livewire\Analytics\Support;

use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Scheduling\WipPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate reads for the Analytics screen. Everything is one grouped query; nothing loads deliverables one by one.
 * Cycle times come from the `deliverable_moved` rows of the audit trail, and before a deliverable's first native move from the
 * legacy CORE_STATUS_CHANGED rows mapped to the new columns (legacy substatus is not in those rows, so history is by main column only)
 * (SQLite date functions, like the rest of the app).
 */
class AnalyticsMetrics
{
    public const WINDOW_DAYS = 90;

    public const THROUGHPUT_WEEKS = 8;

    public function __construct(private ?CarbonInterface $now = null)
    {
        $this->now ??= CarbonImmutable::now();
    }

    /**
     * Average days a deliverable sat in each column, for stays that ended in the last 90 days.
     *
     * @return list<array{status: DeliverableStatus, days: float, stays: int}>
     */
    public function cycleTimePerColumn(): array
    {
        $rows = $this->stays()->get()->keyBy('status');

        return collect(DeliverableStatus::cases())
            ->reject(fn (DeliverableStatus $status): bool => $status === DeliverableStatus::DONE)
            ->map(fn (DeliverableStatus $status): array => [
                'status' => $status,
                'days' => round((float) ($rows[$status->value]->days ?? 0), 1),
                'stays' => (int) ($rows[$status->value]->stays ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Time in client review: the average of finished stays, plus what is waiting right now.
     *
     * @return array{average_days: float, stays: int, waiting_now: int, longest_waiting_days: int}
     */
    public function clientWait(): array
    {
        $row = $this->stays()->where('status', DeliverableStatus::CLIENT_REVIEW->value)->first();
        $waiting = Deliverable::query()->inWorkspace()->inStatus(DeliverableStatus::CLIENT_REVIEW);
        $oldest = (clone $waiting)->whereNotNull('last_sent_to_client_at')->min('last_sent_to_client_at');

        return [
            'average_days' => round((float) ($row->days ?? 0), 1),
            'stays' => (int) ($row->stays ?? 0),
            'waiting_now' => (clone $waiting)->count(),
            'longest_waiting_days' => $oldest !== null ? (int) CarbonImmutable::parse($oldest)->startOfDay()->diffInDays($this->now->startOfDay()) : 0,
        ];
    }

    /**
     * Deliverables closed per week, oldest week first, empty weeks included.
     *
     * @return list<array{week_start: string, count: int}>
     */
    public function weeklyThroughput(): array
    {
        $firstWeek = $this->now->startOfWeek()->subWeeks(self::THROUGHPUT_WEEKS - 1);

        $perDay = Deliverable::query()
            ->inStatus(DeliverableStatus::DONE)
            ->where('closed_at', '>=', $firstWeek->toDateTimeString())
            ->selectRaw('date(closed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $weeks = [];
        for ($week = 0; $week < self::THROUGHPUT_WEEKS; $week++) {
            $start = $firstWeek->addWeeks($week);
            $weeks[$start->toDateString()] = 0;
        }

        foreach ($perDay as $day => $total) {
            $weekStart = CarbonImmutable::parse($day)->startOfWeek()->toDateString();
            if (isset($weeks[$weekStart])) {
                $weeks[$weekStart] += (int) $total;
            }
        }

        return collect($weeks)->map(fn (int $count, string $start): array => ['week_start' => $start, 'count' => $count])->values()->all();
    }

    /**
     * Work in design right now against each designer's limit (blocked cards do not count, like the WIP rule).
     *
     * @return list<array{user: User, wip: int, limit: int}>
     */
    public function wipPerDesigner(): array
    {
        $counts = Deliverable::query()
            ->inWorkspace()
            ->inStatus(DeliverableStatus::IN_DESIGN)
            ->notBlocked()
            ->whereNotNull('assignee_id')
            ->selectRaw('assignee_id, COUNT(*) as total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        return User::query()
            ->designTeam()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'user' => $user,
                'wip' => (int) ($counts[$user->id] ?? 0),
                'limit' => app(WipPolicy::class)->limitFor($user),
            ])
            ->all();
    }

    /**
     * What the old Archived page summarised: total closed, close reasons, average days to close and the top designer.
     *
     * @return array{total: int, reasons: list<array{reason: CloseReason, count: int}>, average_days: float, top_designer: ?array{name: string, count: int}}
     */
    public function closedKpis(): array
    {
        $closed = fn () => Deliverable::query()->inStatus(DeliverableStatus::DONE);

        $reasons = $closed()->whereNotNull('close_reason')
            ->selectRaw('close_reason, COUNT(*) as total')->groupBy('close_reason')->orderByDesc('total')
            ->pluck('total', 'close_reason');

        $average = $closed()->whereNotNull('closed_at')
            ->selectRaw('AVG(julianday(closed_at) - julianday(created_at)) as days')->value('days');

        $top = $closed()->whereNotNull('assignee_id')
            ->selectRaw('assignee_id, COUNT(*) as total')->groupBy('assignee_id')->orderByDesc('total')->first();
        $topUser = $top !== null ? User::query()->find($top->assignee_id, ['id', 'name']) : null;

        return [
            'total' => $closed()->count(),
            'reasons' => $reasons
                ->map(fn ($count, $code): ?array => CloseReason::tryFrom((string) $code) ? ['reason' => CloseReason::from((string) $code), 'count' => (int) $count] : null)
                ->filter()->values()->all(),
            'average_days' => round(max(0.0, (float) $average), 1),
            'top_designer' => $topUser !== null ? ['name' => $topUser->name, 'count' => (int) $top->total] : null,
        ];
    }

    /**
     * Finished stays per status in the window: how long each lasted until the next move of that deliverable.
     *
     * @return Builder
     */
    private function stays()
    {
        $since = $this->now->subDays(self::WINDOW_DAYS)->toDateTimeString();

        $legacyStatus = "CASE
            WHEN e.new_value = 'ENTRANTE' THEN 'intake'
            WHEN e.new_value LIKE '%ORDERS RECEIVED' THEN 'ready'
            WHEN e.new_value = 'TO DO TODAY' THEN 'in_design'
            WHEN e.new_value = 'ENVIADO A CAMILA' THEN 'internal_review'
            WHEN e.new_value = 'ENVIADO AL CLIENTE' THEN 'client_review'
            WHEN e.new_value LIKE 'EN PRODUCC%' THEN 'in_production'
            WHEN e.new_value = 'ARCHIVED' THEN 'done'
        END";

        $events = DB::query()->fromSub(
            DB::table('order_events')
                ->where('event_type', 'deliverable_moved')
                ->select('id', 'order_id', 'created_at')
                ->selectRaw('new_value as moved_to')
                ->unionAll(
                    DB::table('order_events as e')
                        ->where('e.event_type', 'CORE_STATUS_CHANGED')
                        ->whereRaw("e.created_at < COALESCE((SELECT MIN(m.created_at) FROM order_events m WHERE m.order_id = e.order_id AND m.event_type = 'deliverable_moved'), '9999-12-31')")
                        ->select('e.id', 'e.order_id', 'e.created_at')
                        ->selectRaw($legacyStatus.' as moved_to'),
                ),
            'events',
        )->selectRaw('moved_to as new_value, created_at, LEAD(created_at) OVER (PARTITION BY order_id ORDER BY created_at, id) as left_at');

        $moves = DB::query()->fromSub($events, 'ordered')->whereNotNull('new_value')->select('new_value', 'created_at', 'left_at');

        return DB::query()->fromSub($moves, 'moves')
            ->whereNotNull('left_at')
            ->where('left_at', '>=', $since)
            ->selectRaw('new_value as status, AVG(julianday(left_at) - julianday(created_at)) as days, COUNT(*) as stays')
            ->groupBy('new_value');
    }
}
