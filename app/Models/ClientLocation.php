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
        'phone',
        'email',
        'manager_name',
        'manager_phone',
        'notes',
    ];

    public static function formatPhoneNumber(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '1') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
        }

        return $phone;
    }

    public static function isValidPhoneNumber(?string $phone): bool
    {
        if (empty(trim($phone ?? ''))) {
            return true;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '1') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = self::formatPhoneNumber($value);
    }

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
