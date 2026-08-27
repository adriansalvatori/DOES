<?php

namespace App\Models;

use App\Enums\RelatedTaskType;
use App\Services\AutomationEngine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RelatedTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'title',
        'type',
        'status',
        'assignee_id',
        'scheduled_date',
        'due_date',
        'completed_at',
        'trigger_type',
        'priority',
        'is_work_task',
    ];

    protected $casts = [
        'type' => RelatedTaskType::class,
        'scheduled_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'is_work_task' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Designer::class, 'assignee_id');
    }

    protected static function booted(): void
    {
        static::created(function (RelatedTask $task) {
            if ($task->order && ! $task->order->in_workspace) {
                $task->order->update([
                    'in_workspace' => true,
                ]);
            }

            if ($task->order_id) {
                $alreadyLogged = OrderEvent::where('order_id', $task->order_id)
                    ->where('created_at', '>=', now()->subSeconds(2))
                    ->where('metadata->task_id', $task->id)
                    ->exists();

                if (! $alreadyLogged) {
                    $actor = $task->trigger_type ? 'AutomationEngine' : (auth()->user()?->name ?? 'Sistema');
                    $taskTypeStr = is_string($task->type) ? $task->type : $task->type?->value;

                    OrderEvent::create([
                        'order_id' => $task->order_id,
                        'event_type' => 'AUTOMATIC_TASK_TRIGGERED',
                        'actor' => $actor,
                        'new_value' => $task->title,
                        'metadata' => [
                            'task_id' => $task->id,
                            'task_title' => $task->title,
                            'task_type' => $taskTypeStr,
                            'trigger_type' => $task->trigger_type ?? 'SYSTEM_AUTOMATION',
                            'priority' => $task->priority ?? 'normal',
                        ],
                    ]);
                }
            }

            if ($task->order) {
                app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($task->order);
            }
        });

        static::updated(function (RelatedTask $task) {
            if ($task->order) {
                app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($task->order);
            }
        });

        static::deleted(function (RelatedTask $task) {
            if ($task->order) {
                app(AutomationEngine::class)->evaluateSubtaskCompletionAutoDone($task->order);
            }
        });
    }

    public function isDone(): bool
    {
        return $this->status === 'done' || $this->completed_at !== null;
    }

    public function isSystemTask(): bool
    {
        return ! $this->is_work_task || $this->trigger_type !== null;
    }

    public function isWorkTask(): bool
    {
        return (bool) $this->is_work_task && $this->trigger_type === null;
    }
}
