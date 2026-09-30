<?php

namespace App\Models;

use App\Enums\CoreStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Designer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'color_type',
        'hex_color',
        'trello_member_id',
        'active',
        'is_lead',
        'is_external',
        'queue_status_value',
        'aliases',
        'phone',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_lead' => 'boolean',
        'is_external' => 'boolean',
        'aliases' => 'array',
    ];

    public function getContactPhoneAttribute(): ?string
    {
        return $this->phone ?: $this->user?->phone;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'designer_order')->withTimestamps();
    }

    public function relatedTasks(): HasMany
    {
        return $this->hasMany(RelatedTask::class, 'assignee_id');
    }

    public static function getLeadDesigner(): Designer
    {
        return static::where('active', true)->where('is_lead', true)->first()
            ?? static::where('active', true)->where(function ($q) {
                $q->where('name', 'like', '%Eural%')
                    ->orWhere('name', 'like', '%eural%')
                    ->orWhere('slug', 'euraliz');
            })->first()
            ?? static::where('active', true)->first()
            ?? static::first();
    }

    public static function findByAliasOrName(?string $identifier): ?Designer
    {
        if (empty($identifier)) {
            return null;
        }

        $clean = mb_strtolower(trim($identifier));

        return static::where('active', true)
            ->get()
            ->first(function (Designer $designer) use ($clean) {
                if (mb_strtolower($designer->name) === $clean) {
                    return true;
                }

                if ($designer->slug && $designer->slug === Str::slug($clean)) {
                    return true;
                }

                $aliases = is_array($designer->aliases) ? $designer->aliases : json_decode($designer->aliases ?? '[]', true);
                if (! empty($aliases)) {
                    foreach ($aliases as $alias) {
                        $aliasClean = mb_strtolower(trim($alias));
                        if ($aliasClean === $clean || str_contains($clean, $aliasClean) || str_contains($aliasClean, $clean)) {
                            return true;
                        }
                    }
                }

                return false;
            });
    }

    public function getQueueStatus(): CoreStatus
    {
        if ($this->queue_status_value && $status = CoreStatus::tryFrom($this->queue_status_value)) {
            return $status;
        }

        $slug = $this->slug ?: Str::slug($this->name ?? '');

        if (str_contains($slug, 'adrian') || str_contains($slug, 'adri')) {
            return CoreStatus::ADRIAN_ORDERS_RECEIVED;
        }

        if (str_contains($slug, 'cesar') || str_contains($slug, 'ces')) {
            return CoreStatus::CESAR_ORDERS_RECEIVED;
        }

        return CoreStatus::EURALIZ_ORDERS_RECEIVED;
    }

    public function getColorTypeAttribute(): string
    {
        if (! empty($this->attributes['color_type'])) {
            return $this->attributes['color_type'];
        }

        $name = mb_strtolower($this->name ?? '');

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

    public function getIsExternalAttribute(): bool
    {
        if (! empty($this->attributes['is_external'])) {
            return true;
        }

        $name = mb_strtolower($this->name ?? '');

        return str_contains($name, 'extern') || in_array($this->color_type, ['yellow', 'amber'], true);
    }

    public function getDotColorClassAttribute(): string
    {
        return match ($this->color_type) {
            'magenta' => 'bg-fuchsia-500',
            'cyan' => 'bg-cyan-500',
            'green', 'emerald' => 'bg-emerald-500',
            'amber', 'yellow' => 'bg-amber-400',
            'indigo' => 'bg-indigo-500',
            default => 'bg-cyan-500',
        };
    }

    public function getBadgeStyleAttribute(): string
    {
        return match ($this->color_type) {
            'magenta' => 'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-300 font-semibold',
            'cyan' => 'bg-cyan-100 text-cyan-800 border-cyan-300 font-semibold',
            'green', 'emerald' => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-semibold',
            'amber', 'yellow' => 'bg-amber-100 text-amber-800 border-amber-300 font-semibold',
            'indigo' => 'bg-indigo-100 text-indigo-800 border-indigo-300 font-semibold',
            default => 'bg-cyan-100 text-cyan-800 border-cyan-300 font-semibold',
        };
    }

    public function isSamePersonAs(Designer|int|null $other): bool
    {
        if (! $other) {
            return false;
        }

        $otherId = $other instanceof Designer ? $other->id : (int) $other;
        if ($this->id === $otherId) {
            return true;
        }

        if ($this->user_id && $other instanceof Designer && $other->user_id) {
            return $this->user_id === $other->user_id;
        }

        return false;
    }
}
