<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemTaskConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_type',
        'title',
        'category',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function categoryBadgeStyle(): string
    {
        return match ($this->category) {
            'Cliente' => 'bg-sky-50 text-sky-700 border-sky-200',
            'Interno' => 'bg-purple-50 text-purple-700 border-purple-200',
            'Producción' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'Resolver' => 'bg-rose-50 text-rose-700 border-rose-200',
            default => 'bg-stone-100 text-stone-700 border-stone-200',
        };
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        $words = array_values(array_filter(preg_split('/\s+/', $search), fn ($w) => $w !== ''));
        if (empty($words)) {
            return $query;
        }

        return $query->where(function ($q) use ($words) {
            foreach ($words as $word) {
                $term = '%'.addcslashes($word, '%_\\').'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            }
        });
    }
}
