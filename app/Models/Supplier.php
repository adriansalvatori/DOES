<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'website_url',
        'contact_info',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the badge style matching an InstallationType with the same name.
     */
    public function getBadgeStyle(): array
    {
        return InstallationType::getStyleFor($this->name);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
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

        return $query->where('name', 'like', $term);
    }
}
