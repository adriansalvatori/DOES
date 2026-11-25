<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
{
    protected $model = Phase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'name' => fake()->words(2, true),
            'position' => 1,
            'promised_date' => now()->addDays(7)->toDateString(),
        ];
    }
}
