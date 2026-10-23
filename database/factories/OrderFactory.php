<?php

namespace Database\Factories;

use App\Enums\CoreStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'task_name' => fake()->sentence(3),
            'wo_number' => 'WO '.fake()->unique()->numberBetween(1000, 9999),
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'current_due_date' => now()->addDays(3)->toDateString(),
            'approved' => false,
        ];
    }
}
