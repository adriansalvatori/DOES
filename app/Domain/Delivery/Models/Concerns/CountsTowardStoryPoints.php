<?php

namespace App\Domain\Delivery\Models\Concerns;

use App\Domain\Delivery\Support\PointsEstimator;
use BackedEnum;

/**
 * A task row carries Fibonacci points (the domain Task and the legacy RelatedTask share the related_tasks table).
 * Points are inferred when a task is added to work outside the backlog, and the deliverable's points, its story
 * total, are summed again whenever a task is added, changed, removed or restored.
 */
trait CountsTowardStoryPoints
{
    public static function bootCountsTowardStoryPoints(): void
    {
        static::creating(function (self $task): void {
            if ($task->points !== null || $task->order_id === null) {
                return;
            }

            $kind = $task->kind instanceof BackedEnum ? $task->kind->value : $task->kind;
            $type = $task->type instanceof BackedEnum ? $task->type->value : $task->type;
            $task->points = app(PointsEstimator::class)->estimateNewTask((int) $task->order_id, $task->title ?: $task->title_key, $type, $kind);
        });

        static::saved(function (self $task): void {
            if ($task->wasRecentlyCreated || $task->wasChanged(['points', 'order_id', 'deleted_at'])) {
                self::syncStoryPoints($task);
            }
        });

        static::deleted(fn (self $task) => self::syncStoryPoints($task));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (self $task) => self::syncStoryPoints($task));
        }
    }

    private static function syncStoryPoints(self $task): void
    {
        $estimator = app(PointsEstimator::class);

        foreach (array_unique(array_filter([$task->order_id, $task->getOriginal('order_id')])) as $deliverableId) {
            $estimator->syncStory((int) $deliverableId);
        }
    }
}
