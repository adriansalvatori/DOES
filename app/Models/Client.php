<?php

namespace App\Models;

use App\Enums\CoreStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'website',
        'notes',
    ];

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = mb_strtoupper(trim($value), 'UTF-8');
    }

    protected static function booted(): void
    {
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
