<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\ApprovalSource;
use App\Models\User;
use Database\Factories\Domain\Delivery\Models\ApprovalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    /** @use HasFactory<ApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'deliverable_id',
        'source',
        'measurements_confirmed',
        'estimate_approved',
        'note',
        'evidence_path',
        'approved_by',
        'approved_at',
        'reset_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source' => ApprovalSource::class,
            'measurements_confirmed' => 'boolean',
            'estimate_approved' => 'boolean',
            'approved_at' => 'datetime',
            'reset_at' => 'datetime',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeStanding(Builder $query): Builder
    {
        return $query->whereNull('reset_at');
    }
}
