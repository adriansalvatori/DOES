<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}
