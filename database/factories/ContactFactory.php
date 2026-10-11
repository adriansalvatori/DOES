<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->name(), 'UTF-8'),
            'phone' => sprintf('(%d) %d-%04d', fake()->numberBetween(200, 999), fake()->numberBetween(100, 999), fake()->numberBetween(1000, 9999)),
            'email' => fake()->safeEmail(),
            'preferred_channel' => fake()->randomElement(['whatsapp', 'call', 'email', 'any']),
            'preferred_language' => fake()->randomElement(['es', 'en', 'both']),
            'role_title' => fake()->randomElement(['Gerente', 'Project Manager', 'Director', 'Marketing']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
