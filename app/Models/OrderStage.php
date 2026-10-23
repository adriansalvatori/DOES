<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'name',
        'due_date',
        'sort_order',
        'status',
        'notes',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'status' => 'pending',
    ];

    protected $casts = [
        'due_date' => 'date',
        'sort_order' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(OrderElement::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RelatedTask::class, 'order_stage_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isReadyForProduction(): bool
    {
        return $this->status === 'ready_for_production';
    }

    public function isAllApproved(): bool
    {
        $elems = $this->elements;
        if ($elems->isEmpty()) {
            return false;
        }

        return $elems->every(fn (OrderElement $el) => $el->is_approved);
    }

    public function isAllAltaReady(): bool
    {
        $elems = $this->elements;
        if ($elems->isEmpty()) {
            return false;
        }

        return $elems->every(fn (OrderElement $el) => in_array($el->status, ['ready_for_production', 'produced'], true));
    }
}
