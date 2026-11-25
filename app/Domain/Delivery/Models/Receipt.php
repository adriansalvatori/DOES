<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Models\User;
use Database\Factories\Domain\Delivery\Models\ReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    protected $fillable = [
        'deliverable_id',
        'method',
        'logged_by',
        'received_at',
        'note',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'method' => FulfillmentMethod::class,
            'received_at' => 'datetime',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
