<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DueDateHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'previous_due_date',
        'new_due_date',
        'reason',
        'trigger_event',
        'created_by',
        'client_promised_date',
    ];

    protected $casts = [
        'previous_due_date' => 'date',
        'new_due_date' => 'date',
        'client_promised_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
