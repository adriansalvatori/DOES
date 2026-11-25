<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'WO '.fake()->unique()->numberBetween(10000, 99999),
            'description' => fake()->sentence(),
            'promised_date' => now()->addDays(7)->toDateString(),
            'in_backlog' => false,
        ];
    }

    public function withoutNumber(): static
    {
        return $this->state(fn () => ['number' => null]);
    }

    public function inBacklog(): static
    {
        return $this->state(fn () => ['in_backlog' => true]);
    }
}
