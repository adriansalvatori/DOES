<?php

namespace App\Observers;

use App\Enums\SubtaskCategory;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Services\ActionRequiredResolverService;
use App\Services\AutomationEngine;
use Illuminate\Support\Facades\Log;

class RelatedTaskObserver
{
    /**
     * Handle the RelatedTask "saving" event to ensure completed_at stays consistent.
     */
    public function saving(RelatedTask $task): void
    {
        if ($task->status === 'done' && $task->completed_at === null) {
            $task->completed_at = now();
        } elseif ($task->status !== 'done' && $task->completed_at !== null && $task->isDirty('status')) {
            $task->completed_at = null;
        }

        if ($task->isFollowUp()) {
            $task->is_work_task = false;
            $task->category = SubtaskCategory::MANAGEMENT;
            $task->return_core_status = null;
        }
    }

    /**
     * Handle the RelatedTask "created" event.
     */
    public function created(RelatedTask $task): void
    {
        if ($task->isDone()) {
            $this->logCompletionEvent($task);
        }
    }

    /**
     * Handle the RelatedTask "updated" event.
     */
    public function updated(RelatedTask $task): void
    {
        $statusChanged = $task->wasChanged('status');
        $completedAtChanged = $task->wasChanged('completed_at');

        if ($statusChanged || $completedAtChanged) {
            if ($task->order) {
                app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($task->order);
            }

            if ($task->isDone()) {
                $this->logCompletionEvent($task);

                try {
                    app(ActionRequiredResolverService::class)->handleRelatedTaskCompleted($task);
                } catch (\Throwable $e) {
                    Log::warning("RelatedTaskObserver failed auto-resolution evaluation for task #{$task->id}: ".$e->getMessage());
                }
            } elseif ($task->getOriginal('status') === 'done' || $task->getOriginal('completed_at') !== null) {
                if ($task->order_id) {
                    OrderEvent::where('order_id', $task->order_id)
                        ->where('event_type', 'SUBTASK_COMPLETED')
                        ->where(function ($q) use ($task) {
                            $q->where('metadata->task_id', $task->id)
                                ->orWhere('new_value', $task->title);
                        })
                        ->delete();
                }
            }
        }
    }

    private function logCompletionEvent(RelatedTask $task): void
    {
        if (! $task->order_id) {
            return;
        }

        $alreadyLogged = OrderEvent::where('order_id', $task->order_id)
            ->where('event_type', 'SUBTASK_COMPLETED')
            ->where(function ($q) use ($task) {
                $q->where('metadata->task_id', $task->id)
                    ->orWhere('new_value', $task->title);
            })
            ->where('created_at', '>=', now()->subSeconds(5))
            ->exists();

        if (! $alreadyLogged) {
            OrderEvent::create([
                'order_id' => $task->order_id,
                'event_type' => 'SUBTASK_COMPLETED',
                'actor' => auth()->user()?->name ?? ($task->assignee?->name ?? 'Usuario'),
                'new_value' => $task->title,
                'metadata' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'date' => $task->scheduled_date?->toDateString(),
                ],
            ]);
        }
    }
}
