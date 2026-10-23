<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_stage_id',
        'name',
        'dimensions',
        'material',
        'quantity',
        'sort_order',
        'status',
        'is_approved',
        'approved_at',
        'approval_type',
        'approval_note',
        'file_url',
        'production_notes',
        'completed_at',
    ];

    protected $attributes = [
        'quantity' => 1,
        'sort_order' => 0,
        'status' => 'draft',
        'is_approved' => false,
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(OrderStage::class, 'order_stage_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RelatedTask::class, 'order_element_id');
    }

    public function markApproved(string $approvalType = 'cliente', ?string $note = null): void
    {
        $this->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approval_type' => $approvalType,
            'approval_note' => $note,
            'status' => in_array($this->status, ['ready_for_production', 'produced'], true) ? $this->status : 'approved',
        ]);
    }

    public function markReadyForProduction(?string $fileUrl = null, ?string $notes = null): void
    {
        $data = [
            'status' => 'ready_for_production',
            'completed_at' => now(),
        ];

        if ($fileUrl !== null) {
            $data['file_url'] = $fileUrl;
        }

        if ($notes !== null) {
            $data['production_notes'] = $notes;
        }

        $this->update($data);
    }

    public function isReadyForProduction(): bool
    {
        return $this->status === 'ready_for_production' || $this->status === 'produced';
    }

    public function isInAlta(): bool
    {
        return $this->status === 'in_alta' || $this->status === 'ready_for_production';
    }
}
