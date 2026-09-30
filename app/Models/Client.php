<?php

namespace App\Models;

use App\Enums\CoreStatus;
use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'aliases',
        'phones',
        'website',
        'notes',
        'portal_token',
    ];

    protected $casts = [
        'aliases' => 'array',
        'phones' => 'array',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? mb_strtoupper($value, 'UTF-8') : null,
            set: fn (?string $value) => $value !== null ? mb_strtoupper($value, 'UTF-8') : null,
        );
    }

    /**
     * Check if a given search term matches this client's primary name or any of its aliases.
     */
    public function matchesNameOrAlias(string $term): bool
    {
        $termClean = mb_strtolower(trim($term), 'UTF-8');
        if (mb_strtolower(trim($this->name), 'UTF-8') === $termClean) {
            return true;
        }

        if (is_array($this->aliases)) {
            foreach ($this->aliases as $alias) {
                if (mb_strtolower(trim($alias), 'UTF-8') === $termClean) {
                    return true;
                }
            }
        }

        return false;
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
                        ->orWhere('website', 'like', $term)
                        ->orWhere('notes', 'like', $term)
                        ->orWhere('aliases', 'like', $term)
                        ->orWhere('phones', 'like', $term)
                        ->orWhereHas('contacts', fn ($cq) => $cq->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term))
                        ->orWhereHas('locations', fn ($lq) => $lq->where('name', 'like', $term)->orWhere('address', 'like', $term));
                });
            }
        });
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = mb_strtoupper(trim($value), 'UTF-8');
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->portal_token)) {
                $client->portal_token = Str::random(32);
            }
        });

        static::deleting(function (Client $client) {
            if ($client->isForceDeleting()) {
                return;
            }

            if (! str_contains($client->name, '[DELETED')) {
                $client->name = $client->name.' [DELETED-'.$client->id.']';
                $client->saveQuietly();
            }
        });
    }

    public function getPortalUrlAttribute(): string
    {
        if (empty($this->portal_token)) {
            $this->portal_token = Str::random(32);
            $this->saveQuietly();
        }

        return route('client.portal', ['token' => $this->portal_token]);
    }

    public function getQrSvg(int $size = 240): string
    {
        return app(QrCodeService::class)->generateSvg($this->portal_url, $size);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ClientLocation::class)->orderBy('name');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ClientLink::class)->orderBy('department')->orderBy('label');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->where('in_workspace', true);
    }

    public function activeOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('in_workspace', true)->where('core_status', '!=', CoreStatus::ARCHIVED);
    }

    public function archivedOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('in_workspace', true)->where('core_status', CoreStatus::ARCHIVED);
    }

    public function allOrders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getGroupedLinksAttribute(): array
    {
        $grouped = [];
        foreach ($this->links as $link) {
            $dept = $link->department ?: 'General';
            $grouped[$dept][] = $link;
        }

        return $grouped;
    }
}
