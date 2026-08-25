<?php

namespace App\Models;

use App\Enums\CoreStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'name',
        'address',
        'manager_name',
        'manager_phone',
        'notes',
    ];

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = mb_strtoupper(trim($value), 'UTF-8');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
}
