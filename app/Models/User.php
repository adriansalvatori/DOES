<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'preferences',
        'avatar_url',
        'active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'preferences' => 'array',
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function designer(): HasOne
    {
        return $this->hasOne(Designer::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isCoordinator(): bool
    {
        return $this->role === UserRole::COORDINATOR;
    }

    public function isDesigner(): bool
    {
        return $this->role === UserRole::DESIGNER;
    }

    public function isSales(): bool
    {
        return $this->role === UserRole::SALES;
    }

    public function isLeadDesigner(): bool
    {
        return (bool) $this->designer?->is_lead;
    }

    public function canDeleteOrders(): bool
    {
        return $this->isAdmin() || $this->isCoordinator() || $this->isLeadDesigner();
    }

    public function canManageWorkOrders(): bool
    {
        return $this->isAdmin() || $this->isCoordinator() || $this->isLeadDesigner();
    }

    public function canManagePricing(): bool
    {
        return $this->isAdmin() || $this->isCoordinator();
    }

    public function hasRole(UserRole|string $role): bool
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;

        return $this->role?->value === $roleValue;
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        $initials = '';

        foreach ($words as $w) {
            if ($w !== '') {
                $initials .= mb_substr($w, 0, 1);
            }
        }

        return mb_strtoupper(mb_substr($initials, 0, 2)) ?: 'US';
    }

    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences ?? [], $key, $default);
    }

    public function setPreference(string $key, mixed $value): void
    {
        $prefs = $this->preferences ?? [];
        data_set($prefs, $key, $value);
        $this->update(['preferences' => $prefs]);
    }
}
