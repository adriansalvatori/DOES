<?php

namespace App\Models;

use App\Enums\RelatedTaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelatedTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'title',
        'type',
        'status',
        'assignee_id',
        'due_date',
        'completed_at',
        'trigger_type',
        'priority',
    ];

    protected $casts = [
        'type' => RelatedTaskType::class,
        'due_date' => 'date',
        'completed_at' => 'datetime',
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
            if ($task->order && !$task->order->in_workspace) {
                $task->order->update([
                    'in_workspace' => true,
                ]);
            }
        });
    }

    public function isDone(): bool
    {
        return $this->status === 'done' || $this->completed_at !== null;
    }
}
