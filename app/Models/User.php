<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Delivery\Models\Deliverable;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'roles',
        'phone',
        'preferences',
        'avatar_url',
        'active',
        'last_login_at',
        'wip_limit',
        'is_reviewer',
        'is_lead',
        'designer_id',
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
            'roles' => AsEnumCollection::of(UserRole::class),
            'preferences' => 'array',
            'active' => 'boolean',
            'last_login_at' => 'datetime',
            'wip_limit' => 'integer',
            'is_reviewer' => 'boolean',
            'is_lead' => 'boolean',
        ];
    }

    /**
     * Keep `role` (the primary role) in step with the `roles` list.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('role') && ! $user->isDirty('roles')) {
                $user->roles = null;

                return;
            }

            if ($user->isDirty('roles')) {
                $list = $user->roleList();

                if ($list === []) {
                    $user->roles = null;
                } else {
                    $user->role = UserRole::primaryOf($list);
                }
            }
        });
    }

    public function designer(): HasOne
    {
        return $this->hasOne(Designer::class);
    }

    public function assignedDeliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'assignee_id');
    }

    /**
     * Users holding any of the given roles, in the primary column or the roles list.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithAnyRole(Builder $query, UserRole ...$roles): Builder
    {
        return $query->where(function (Builder $holders) use ($roles): void {
            foreach ($roles as $role) {
                $holders->orWhere('role', $role->value)->orWhereJsonContains('roles', $role->value);
            }
        });
    }

    /**
     * Active people who design: users with the designer role plus the design lead (Euralíz is an admin and the lead).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDesignTeam(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->where(fn (Builder $team) => $team
                ->where('role', UserRole::DESIGNER->value)
                ->orWhereJsonContains('roles', UserRole::DESIGNER->value)
                ->orWhere('is_lead', true));
    }

    /**
     * Every role the user holds; falls back to the primary role while `roles` is empty.
     *
     * @return list<UserRole>
     */
    public function roleList(): array
    {
        $roles = $this->roles?->filter()->values()->all() ?? [];

        if ($roles !== []) {
            return $roles;
        }

        return $this->role instanceof UserRole ? [$this->role] : [];
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        $held = array_map(fn (UserRole $role): string => $role->value, $this->roleList());

        foreach ($roles as $role) {
            if (in_array($role instanceof UserRole ? $role->value : $role, $held, true)) {
                return true;
            }
        }

        return false;
    }

    public function isCustomerService(): bool
    {
        return $this->hasRole(UserRole::CUSTOMER_SERVICE);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }

    public function isCoordinator(): bool
    {
        return $this->hasRole(UserRole::COORDINATOR);
    }

    /**
     * Designer-only: has the designer role and no admin or coordinator role (drives the "own work only" restrictions).
     */
    public function isDesigner(): bool
    {
        return $this->hasRole(UserRole::DESIGNER) && ! $this->hasRole(UserRole::ADMIN, UserRole::COORDINATOR);
    }

    /**
     * Whether the person does design work, whatever else they are (designer role or design lead).
     */
    public function designs(): bool
    {
        return $this->hasRole(UserRole::DESIGNER) || (bool) $this->is_lead;
    }

    public function isSales(): bool
    {
        return $this->hasRole(UserRole::SALES);
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
