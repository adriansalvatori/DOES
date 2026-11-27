<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\Priority;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Domain\Delivery\Models\DeliverableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A piece of work inside a work order. Until the legacy tables are renamed it is stored in `orders`.
 */
class Deliverable extends Model
{
    /** @use HasFactory<DeliverableFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    /**
     * Defaults the database also applies, so a new model has them before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 'medium',
        'in_backlog' => false,
    ];

    protected $fillable = [
        'work_order_id',
        'phase_id',
        'status',
        'assignee_id',
        'reviewer_id',
        'planned_for',
        'due_target',
        'size',
        'material',
        'quantity',
        'files_url',
        'trello_card_url',
        'flag_codes',
        'close_reason',
        'closed_at',
        'carry_over_count',
        'in_backlog',
        'sample_of_id',
        'priority',
        'points',
        'client_revision_count',
        'internal_revision_count',
        'last_sent_to_client_at',
        'company_name',
        'task_name',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliverableStatus::class,
            'close_reason' => CloseReason::class,
            'flag_codes' => AsEnumCollection::of(DeliverableFlag::class),
            'planned_for' => 'date',
            'due_target' => 'date',
            'closed_at' => 'datetime',
            'quantity' => 'integer',
            'carry_over_count' => 'integer',
            'in_backlog' => 'boolean',
            'priority' => Priority::class,
            'points' => 'integer',
            'client_revision_count' => 'integer',
            'internal_revision_count' => 'integer',
            'last_sent_to_client_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * The deliverable this one is a production sample of.
     */
    public function sampleOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sample_of_id');
    }

    /**
     * Production samples requested while the client reviewed this deliverable.
     */
    public function samples(): HasMany
    {
        return $this->hasMany(self::class, 'sample_of_id');
    }

    public function isSample(): bool
    {
        return $this->sample_of_id !== null;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'order_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function openBlock(): HasOne
    {
        return $this->hasOne(Block::class)->whereNull('resolved_at')->latestOfMany();
    }

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function openHold(): HasOne
    {
        return $this->hasOne(Hold::class)->whereNull('ended_at')->latestOfMany();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeliverableEvent::class, 'order_id');
    }

    /**
     * @param  DeliverableStatus|list<DeliverableStatus>  $status
     */
    public function scopeInStatus(Builder $query, DeliverableStatus|array $status): Builder
    {
        $codes = array_map(
            fn (DeliverableStatus $each): string => $each->value,
            is_array($status) ? $status : [$status],
        );

        return $query->whereIn($query->qualifyColumn('status'), $codes);
    }

    public function scopeInArea(Builder $query, Area $area): Builder
    {
        return $query->inStatus(DeliverableStatus::inArea($area));
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->whereHas('blocks', fn (Builder $blocks) => $blocks->whereNull('resolved_at'));
    }

    public function scopeNotBlocked(Builder $query): Builder
    {
        return $query->whereDoesntHave('blocks', fn (Builder $blocks) => $blocks->whereNull('resolved_at'));
    }

    public function scopeOnHold(Builder $query): Builder
    {
        return $query->whereHas('holds', fn (Builder $holds) => $holds->whereNull('ended_at'));
    }

    public function scopePlannedFor(Builder $query, CarbonInterface|string $date): Builder
    {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $query->whereDate($query->qualifyColumn('planned_for'), $day);
    }

    public function scopeAssignedTo(Builder $query, User|int $user): Builder
    {
        return $query->where($query->qualifyColumn('assignee_id'), $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * Deliverables out of the backlog: the only ones the working screens, counts and daily automation see.
     */
    public function scopeInWorkspace(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('in_backlog'), false);
    }

    /**
     * Open deliverables still waiting in the backlog. Each deliverable carries its own flag, so a later phase can wait
     * there while an earlier one is worked on.
     */
    public function scopeInBacklog(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('in_backlog'), true)->active();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) use ($query) {
            $column = $query->qualifyColumn('status');

            $inner->whereNull($column)->orWhere($column, '!=', DeliverableStatus::DONE->value);
        });
    }

    public function hasFlag(DeliverableFlag $flag): bool
    {
        return $this->flag_codes?->contains($flag) ?? false;
    }

    public function isUrgent(): bool
    {
        return $this->hasFlag(DeliverableFlag::URGENT);
    }

    public function isBlocked(): bool
    {
        return $this->blocks()->whereNull('resolved_at')->exists();
    }

    public function isOnHold(): bool
    {
        return $this->holds()->whereNull('ended_at')->exists();
    }
}
