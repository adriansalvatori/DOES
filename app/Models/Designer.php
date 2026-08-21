<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'trello_member_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'designer_order')->withTimestamps();
    }

    public function relatedTasks(): HasMany
    {
        return $this->hasMany(RelatedTask::class, 'assignee_id');
    }

    public function getColorTypeAttribute(): string
    {
        $name = mb_strtolower($this->name);

        if (str_contains($name, 'eural') || str_contains($name, 'bravo')) {
            return 'magenta';
        }

        if (str_contains($name, 'cés') || str_contains($name, 'cesar') || str_contains($name, 'guzman')) {
            return 'cyan';
        }

        if (str_contains($name, 'adr') || str_contains($name, 'reinoza')) {
            return 'green';
        }

        return 'yellow';
    }

    public function getDotColorClassAttribute(): string
    {
        return match ($this->color_type) {
            'magenta' => 'bg-fuchsia-500',
            'cyan' => 'bg-cyan-500',
            'green' => 'bg-emerald-500',
            default => 'bg-amber-400',
        };
    }

    public function getBadgeStyleAttribute(): string
    {
        return match ($this->color_type) {
            'magenta' => 'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-300 font-semibold',
            'cyan' => 'bg-cyan-100 text-cyan-800 border-cyan-300 font-semibold',
            'green' => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-semibold',
            default => 'bg-amber-100 text-amber-800 border-amber-300 font-semibold',
        };
    }
}
