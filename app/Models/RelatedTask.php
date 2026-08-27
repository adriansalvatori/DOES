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

    public function isNote(): bool
    {
        return $this->order_id === null;
    }

    public static function cleanTitleForOrder(string $title, Order $order): string
    {
        $rawTitle = trim($title);
        if ($rawTitle === '') {
            return '';
        }

        $candidates = array_filter([
            $order->location_text,
            $order->location_name,
            $order->clientLocation?->name,
            $order->company_name,
            $order->wo_number,
        ], fn ($val) => ! empty($val) && is_string($val));

        $terms = [];
        foreach ($candidates as $cand) {
            $candTrimmed = trim($cand);
            if ($candTrimmed !== '') {
                $terms[] = $candTrimmed;
                if (preg_match_all('/\b[A-Za-z0-9]{2,}\b/u', $candTrimmed, $matches)) {
                    foreach ($matches[0] as $token) {
                        if (! in_array(mb_strtolower($token), ['de', 'la', 'el', 'los', 'las', 'del', 'en', 'y'], true)) {
                            $terms[] = $token;
                        }
                    }
                }
            }
        }

        // Sort terms longest first to avoid partial replacements (e.g. "Talpa 16" before "16")
        usort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $terms = array_values(array_unique($terms));

        $cleaned = $rawTitle;
        foreach ($terms as $term) {
            $quoted = preg_quote($term, '/');
            // Remove with word boundary so internal letters of other words are preserved (e.g. "la" won't match inside "Camila")
            $pattern = '/\b'.$quoted.'\b/iu';
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        // Remove leftover leading/trailing punctuation, dashes, colons, pipes, and whitespace
        $cleaned = preg_replace('/^[\s\-:,|]+|[\s\-:,|]+$/u', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return $rawTitle;
        }

        // Capitalize first character
        return mb_strtoupper(mb_substr($cleaned, 0, 1)).mb_substr($cleaned, 1);
    }

    public function isFollowUp(): bool
    {
        if ($this->type && in_array($this->type, [
            RelatedTaskType::FOLLOW_UP_CLIENTE,
            RelatedTaskType::FOLLOW_UP_CAMILA,
            RelatedTaskType::FOLLOW_UP_ALTA,
        ], true)) {
            return true;
        }

        return str_contains(strtolower($this->title), 'follow up');
    }
}
