<?php

namespace App\Domain\Delivery\Models;

use Database\Factories\Domain\Delivery\Models\DeliverableEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail entry of a deliverable. Until the legacy tables are renamed it is stored in `order_events`.
 */
class DeliverableEvent extends Model
{
    /** @use HasFactory<DeliverableEventFactory> */
    use HasFactory;

    protected $table = 'order_events';

    protected $fillable = [
        'order_id',
        'event_type',
        'actor',
        'previous_value',
        'new_value',
        'metadata',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'order_id');
    }
}
