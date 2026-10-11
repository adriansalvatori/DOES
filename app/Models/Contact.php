<?php

namespace App\Models;

use App\Enums\CoreStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'preferred_channel',
        'preferred_language',
        'role_title',
        'notes',
    ];

    protected $casts = [
        'preferred_channel' => 'string',
        'preferred_language' => 'string',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? mb_strtoupper($value, 'UTF-8') : null,
            set: fn (?string $value) => $value !== null ? mb_strtoupper(trim($value), 'UTF-8') : null,
        );
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = ClientLocation::formatPhoneNumber($value);
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
                    $sub->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('role_title', 'like', $term)
                        ->orWhere('notes', 'like', $term)
                        ->orWhereHas('clients', fn ($cq) => $cq->where('name', 'like', $term));
                });
            }
        });
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        if (empty($this->phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->phone);
        if (empty($digits)) {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return "https://wa.me/{$digits}";
    }

    public function getChannelBadgeAttribute(): array
    {
        return match ($this->preferred_channel) {
            'whatsapp' => ['label' => 'WhatsApp', 'color' => 'emerald', 'icon' => 'message-circle'],
            'call' => ['label' => 'Llamada', 'color' => 'blue', 'icon' => 'phone'],
            'email' => ['label' => 'Email', 'color' => 'amber', 'icon' => 'mail'],
            default => ['label' => 'Cualquiera', 'color' => 'zinc', 'icon' => 'messages-square'],
        };
    }

    public function getLanguageBadgeAttribute(): array
    {
        return match ($this->preferred_language) {
            'en' => ['code' => 'EN', 'flag' => 'EN', 'label' => 'Inglés', 'color' => 'indigo'],
            'both' => ['code' => 'ES / EN', 'flag' => 'ES / EN', 'label' => 'Bilingüe', 'color' => 'purple'],
            default => ['code' => 'ES', 'flag' => 'ES', 'label' => 'Español', 'color' => 'stone'],
        };
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_contact')
            ->withPivot('is_primary', 'role')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->where('in_workspace', true)
            ->where('core_status', '!=', CoreStatus::ARCHIVED);
    }

    public function archivedOrders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->where('core_status', CoreStatus::ARCHIVED);
    }

    public function backlogOrders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->where('in_workspace', false)
            ->where('core_status', '!=', CoreStatus::ARCHIVED);
    }
}
