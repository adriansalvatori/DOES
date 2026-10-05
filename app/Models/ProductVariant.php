<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'supplier_id',
        'name',
        'sku',
        'specs',
        'turnaround_time',
        'website_url',
        'mockup_url',
        'template_url',
        'image_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class, 'product_variant_id')->orderBy('quantity');
    }

    /**
     * Effective supplier: fallback to product supplier if variant supplier is null.
     */
    public function getEffectiveSupplier(): ?Supplier
    {
        return $this->supplier ?: $this->product?->supplier;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->website_url ?: $this->product?->website_url;
    }

    public function getMockupUrl(): ?string
    {
        return $this->mockup_url ?: $this->product?->mockup_url;
    }

    public function getTemplateUrl(): ?string
    {
        return $this->template_url ?: $this->product?->template_url;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url ?: $this->product?->image_url;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
