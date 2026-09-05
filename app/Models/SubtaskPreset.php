<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubtaskPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'emoji',
        'color_theme',
        'sort_order',
        'is_active',
        'is_work_task',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_work_task' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function badgeStyle(): string
    {
        return match ($this->color_theme) {
            'purple' => 'bg-purple-50 text-purple-800 border-purple-200 hover:bg-purple-100',
            'emerald' => 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100',
            'amber' => 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100',
            'rose' => 'bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100',
            'violet' => 'bg-violet-50 text-violet-800 border-violet-200 hover:bg-violet-100',
            'indigo' => 'bg-indigo-50 text-indigo-800 border-indigo-200 hover:bg-indigo-100',
            'stone' => 'bg-stone-100 text-stone-800 border-stone-200 hover:bg-stone-200',
            default => 'bg-sky-50 text-sky-800 border-sky-200 hover:bg-sky-100',
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
                    $sub->where('title', 'like', $term);
                });
            }
        });
    }
}
