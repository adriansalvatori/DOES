<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role' => UserRole::ADMIN,
            'active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function designer(): static
    {
        return $this->state(fn () => ['role' => UserRole::DESIGNER]);
    }

    public function coordinator(): static
    {
        return $this->state(fn () => ['role' => UserRole::COORDINATOR]);
    }

    public function sales(): static
    {
        return $this->state(fn () => ['role' => UserRole::SALES]);
    }

    public function customerService(): static
    {
        return $this->state(fn () => ['role' => UserRole::CUSTOMER_SERVICE]);
    }

    /**
     * Give the user several roles at once; the primary role follows automatically.
     */
    public function withRoles(UserRole ...$roles): static
    {
        return $this->state(fn () => ['roles' => $roles, 'role' => UserRole::primaryOf(array_values($roles))]);
    }

    public function reviewer(): static
    {
        return $this->state(fn () => ['is_reviewer' => true]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
