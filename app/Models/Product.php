<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'description',
        'image_url',
        'possible_sizes',
        'website_url',
        'mockup_url',
        'template_url',
        'default_markup_percent',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'possible_sizes' => 'array',
        'default_markup_percent' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        $term = '%'.addcslashes($search, '%_\\').'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhereHas('category', fn (Builder $cq) => $cq->where('name', 'like', $term))
                ->orWhereHas('variants', fn (Builder $vq) => $vq->where('name', 'like', $term));
        });
    }

    /**
     * Get sizes as an array of strings.
     */
    public function getSizesList(): array
    {
        if (is_array($this->possible_sizes)) {
            return $this->possible_sizes;
        }

        if (empty($this->possible_sizes)) {
            return [];
        }

        return array_map('trim', explode(',', (string) $this->possible_sizes));
    }
}
