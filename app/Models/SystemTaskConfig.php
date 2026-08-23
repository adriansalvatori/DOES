<?php

namespace App\Models;

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
}
