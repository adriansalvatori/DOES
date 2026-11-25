<?php

namespace App\Models;

use Database\Factories\OrderCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A comment on an order, written natively in DOES or imported from a Trello card.
 */
class OrderComment extends Model
{
    /** @use HasFactory<OrderCommentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'author_name',
        'body',
        'trello_action_id',
        'edited_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function getDisplayAuthorNameAttribute(): string
    {
        return $this->user?->name ?: ($this->author_name ?: __('User'));
    }

    public function isImportedFromTrello(): bool
    {
        return ! empty($this->trello_action_id);
    }
}
