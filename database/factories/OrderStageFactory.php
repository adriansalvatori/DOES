<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStage>
 */
class OrderStageFactory extends Factory
{
    protected $model = OrderStage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'name' => 'Etapa '.fake()->numberBetween(1, 5).' - '.fake()->words(2, true),
            'due_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'sort_order' => fake()->numberBetween(0, 10),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
