<?php

namespace App\Application\Delivery\Queries;

use App\Application\Delivery\Data\ResolverGroup;
use App\Application\Delivery\Data\ResolverItem;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ResolverQuery
{
    private const WORK_ORDER_RELATIONS = ['client', 'clientLocation', 'contact'];

    /**
     * Open blocks and holds grouped by work order, groups with the oldest item first.
     *
     * @param  ?int  $ownerId  Only blocks owned by this user (unowned blocks belong to customer service and are kept). Holds are never owned.
     * @return list<ResolverGroup>
     */
    public function run(?int $ownerId = null, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        $blocks = Block::query()->open()
            ->when($ownerId !== null, fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->where('owner_id', $ownerId)->orWhereNull('owner_id'),
            ))
            ->whereHas('deliverable', fn (Builder $deliverables) => $deliverables->inWorkspace())
            ->with(['owner', 'deliverable.workOrder' => fn ($relation) => $relation->with(self::WORK_ORDER_RELATIONS), 'deliverable.assignee'])
            ->get();

        $holds = Hold::query()->open()
            ->whereHas('deliverable', fn (Builder $deliverables) => $deliverables->inWorkspace())
            ->with(['deliverable.workOrder' => fn ($relation) => $relation->with(self::WORK_ORDER_RELATIONS), 'deliverable.assignee'])
            ->get();

        /** @var Collection<int, ResolverItem> $items */
        $items = collect();
        foreach ($blocks as $block) {
            $items->push(new ResolverItem(
                'block', $block->deliverable, $block, $block->reason, $block->note,
                $this->ageInDays($block->raised_at ?? $block->created_at, $now), $block->owner,
                $this->clientPhone($block->deliverable->workOrder),
            ));
        }
        foreach ($holds as $hold) {
            $items->push(new ResolverItem(
                'hold', $hold->deliverable, $hold, $hold->reason, $hold->note,
                $this->ageInDays($hold->started_at ?? $hold->created_at, $now), null,
                $this->clientPhone($hold->deliverable->workOrder),
            ));
        }

        return $items
            ->groupBy(fn (ResolverItem $item): int => $item->deliverable->work_order_id)
            ->map(function (Collection $group): ResolverGroup {
                $sorted = $group->sortByDesc(fn (ResolverItem $item): int => $item->ageInDays)->values();

                return new ResolverGroup($sorted->first()->deliverable->workOrder, $sorted->all(), $sorted->first()->ageInDays);
            })
            ->sortByDesc(fn (ResolverGroup $group): int => $group->oldestAgeInDays)
            ->values()
            ->all();
    }

    private function ageInDays(?CarbonInterface $since, CarbonInterface $now): int
    {
        return $since === null ? 0 : (int) max(0, $since->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()));
    }

    private function clientPhone(?WorkOrder $workOrder): ?string
    {
        if ($workOrder === null) {
            return null;
        }

        return $workOrder->contact?->phone
            ?: $workOrder->clientLocation?->phone
            ?: $this->firstClientPhone($workOrder->client?->phones)
            ?: null;
    }

    /**
     * Client phones are stored as a list of numbers or of {label, phone} entries.
     *
     * @param  list<string|array{label?: string, phone?: string}>|null  $phones
     */
    private function firstClientPhone(?array $phones): ?string
    {
        foreach ($phones ?? [] as $entry) {
            $number = is_array($entry) ? ($entry['phone'] ?? null) : $entry;
            if (filled($number)) {
                return (string) $number;
            }
        }

        return null;
    }
}
