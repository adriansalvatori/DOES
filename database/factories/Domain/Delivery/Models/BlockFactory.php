<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    protected $model = Block::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deliverable_id' => Deliverable::factory(),
            'reason' => BlockReason::MISSING_MEASUREMENTS,
            'raised_at' => now(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'resolved_at' => now(),
            'resolution_note' => fake()->sentence(),
        ]);
    }
}
