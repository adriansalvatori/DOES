<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hold>
 */
class HoldFactory extends Factory
{
    protected $model = Hold::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deliverable_id' => Deliverable::factory(),
            'reason' => HoldReason::PAUSED,
            'started_at' => now(),
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => ['ended_at' => now()]);
    }
}
