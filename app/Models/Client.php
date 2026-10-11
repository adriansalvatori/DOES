<?php

namespace App\Models;

use App\Enums\CoreStatus;
use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'brand_kit',
    ];

    protected $casts = [
        'aliases' => 'array',
        'phones' => 'array',
        'brand_kit' => 'array',
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

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'client_contact')
            ->withPivot('is_primary', 'role')
            ->withTimestamps()
            ->orderByDesc('client_contact.is_primary')
            ->orderBy('contacts.name');
    }

    public function primaryContact(): BelongsToMany
    {
        return $this->contacts()->wherePivot('is_primary', true);
    }

    public function getPrimaryContactAttribute(): ?Contact
    {
        if ($this->relationLoaded('primaryContact')) {
            return $this->getRelation('primaryContact')->first();
        }

        if ($this->relationLoaded('contacts')) {
            return $this->contacts->firstWhere('pivot.is_primary', true) ?: $this->contacts->first();
        }

        return $this->contacts()->wherePivot('is_primary', true)->first() ?: $this->contacts()->first();
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
        return $this->hasMany(Order::class)->where('core_status', CoreStatus::ARCHIVED);
    }

    public function backlogOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('in_workspace', false)->where('core_status', '!=', CoreStatus::ARCHIVED);
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

    public function getBrandKit(): array
    {
        $default = [
            'drive_url' => '',
            'brandbook_url' => '',
            'logo_url' => '',
            'catalog_url' => '',
            'colors' => [],
            'fonts' => [],
            'guidelines' => '',
            'client_notes' => '',
            'custom_links' => [],
        ];

        $data = is_array($this->brand_kit) ? $this->brand_kit : [];

        // If client has legacy ClientLink records and custom_links is empty, populate from client_links
        if (empty($data['custom_links']) && ($this->relationLoaded('links') ? $this->links->isNotEmpty() : $this->links()->exists())) {
            $legacyLinks = $this->links;
            $custom = [];
            foreach ($legacyLinks as $legacy) {
                $custom[] = [
                    'label' => $legacy->label,
                    'url' => $legacy->url,
                    'notes' => $legacy->department ?? '',
                ];
            }
            $data['custom_links'] = $custom;
        }

        return array_merge($default, $data);
    }

    public function hasBrandKit(): bool
    {
        $kit = $this->getBrandKit();

        return ! empty(trim($kit['drive_url'] ?? ''))
            || ! empty(trim($kit['brandbook_url'] ?? ''))
            || ! empty(trim($kit['logo_url'] ?? ''))
            || ! empty(trim($kit['catalog_url'] ?? ''))
            || ! empty($kit['colors'])
            || ! empty($kit['fonts'])
            || ! empty(trim($kit['guidelines'] ?? ''))
            || ! empty(trim($kit['client_notes'] ?? ''))
            || ! empty($kit['custom_links']);
    }
}
