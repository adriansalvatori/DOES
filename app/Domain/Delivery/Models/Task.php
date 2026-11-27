<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Models\Concerns\CountsTowardStoryPoints;
use App\Models\User;
use Database\Factories\Domain\Delivery\Models\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Work item of a deliverable. Until the legacy tables are renamed it is stored in `related_tasks`.
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use CountsTowardStoryPoints, HasFactory, SoftDeletes;

    protected $table = 'related_tasks';

    protected $fillable = [
        'order_id',
        'title',
        'status',
        'due_date',
        'scheduled_date',
        'assignee_user_id',
        'sort_order',
        'completed_at',
        'kind',
        'advances_to',
        'required',
        'title_key',
        'title_params',
        'points',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => TaskKind::class,
            'advances_to' => DeliverableStatus::class,
            'required' => 'boolean',
            'title_params' => 'array',
            'due_date' => 'date',
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
            'points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'order_id');
    }

    /**
     * The person the task is planned for (Plan week). Empty means it follows the deliverable's designer.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /**
     * Who does this task: its own person, else the deliverable's designer.
     */
    public function effectiveAssigneeId(): ?int
    {
        return $this->assignee_user_id ?? $this->deliverable?->assignee_id;
    }

    public function isGate(): bool
    {
        return in_array($this->kind, [TaskKind::GATE_SEND_PROOF, TaskKind::GATE_PREPARE_PRODUCTION_FILES, TaskKind::GATE_CUSTOM], true);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }
}
