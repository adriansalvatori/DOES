<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Deliverable::factory(),
            'title' => fake()->sentence(3),
            'status' => 'todo',
            'kind' => TaskKind::WORK,
            'required' => false,
        ];
    }

    public function gate(TaskKind $kind, DeliverableStatus $advancesTo): static
    {
        return $this->state(fn () => [
            'kind' => $kind,
            'advances_to' => $advancesTo,
            'required' => true,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => 'done', 'completed_at' => now()]);
    }
}
