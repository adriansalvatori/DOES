<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\HoldReason;
use Database\Factories\Domain\Delivery\Models\HoldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hold extends Model
{
    /** @use HasFactory<HoldFactory> */
    use HasFactory;

    protected $fillable = [
        'deliverable_id',
        'reason',
        'note',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'reason' => HoldReason::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }
}
