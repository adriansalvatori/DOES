<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Retrieve a setting value by key, with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Store or update a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Retrieve the configured approval manager User.
     */
    public static function getManagerUser(): ?User
    {
        $managerId = static::get('manager_id');
        if ($managerId) {
            $user = User::find($managerId);
            if ($user) {
                return $user;
            }
        }

        return User::where('name', 'like', '%Gabriela%')->first()
            ?? User::find(8);
    }

    /**
     * Retrieve the configured approval manager display name.
     */
    public static function getManagerName(): string
    {
        $customName = static::get('manager_name');
        if (! empty($customName)) {
            return trim((string) $customName);
        }

        $manager = static::getManagerUser();
        if ($manager && ! empty($manager->name)) {
            $parts = explode(' ', trim($manager->name));

            return $parts[0] ?: 'Gabriela';
        }

        return 'Gabriela';
    }
}
