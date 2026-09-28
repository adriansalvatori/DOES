<?php

namespace App\Observers;

use App\Models\RelatedTask;
use App\Services\ActionRequiredResolverService;
use App\Services\AutomationEngine;
use Illuminate\Support\Facades\Log;

class RelatedTaskObserver
{
    /**
     * Handle the RelatedTask "updated" event.
     */
    public function updated(RelatedTask $task): void
    {
        if ($task->wasChanged('status')) {
            if ($task->order) {
                app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($task->order);
            }

            if ($task->status === 'done') {
                try {
                    app(ActionRequiredResolverService::class)->handleRelatedTaskCompleted($task);
                } catch (\Throwable $e) {
                    Log::warning("RelatedTaskObserver failed auto-resolution evaluation for task #{$task->id}: ".$e->getMessage());
                }
            }
        }
    }
}
