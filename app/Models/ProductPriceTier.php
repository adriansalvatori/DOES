<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'quantity',
        'production_cost',
        'markup_percent',
        'calculated_price',
        'final_price',
        'unit_price',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'production_cost' => 'decimal:2',
        'markup_percent' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'unit_price' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductPriceTier $tier) {
            $tier->recalculate();
        });
    }

    /**
     * Recalculate calculated_price, final_price, and unit_price based on cost and markup.
     */
    public function recalculate(): void
    {
        $cost = (float) ($this->production_cost ?? 0.0);
        $markup = (float) ($this->markup_percent ?? 50.0);
        $qty = (int) ($this->quantity ?? 1);

        $this->calculated_price = round($cost * (1 + ($markup / 100)), 2);
        $this->final_price = $this->calculated_price;
        $this->unit_price = $qty > 0 ? round($this->final_price / $qty, 4) : 0.0;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
