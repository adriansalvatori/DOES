<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deliverable>
 */
class DeliverableFactory extends Factory
{
    protected $model = Deliverable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'status' => DeliverableStatus::INTAKE,
            'company_name' => fake()->company(),
            'task_name' => fake()->sentence(3),
            'due_target' => now()->addDays(3)->toDateString(),
            'carry_over_count' => 0,
            'in_backlog' => false,
            'priority' => 'medium',
        ];
    }

    public function inStatus(DeliverableStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assignee_id' => $user->getKey()]);
    }

    public function plannedFor(string $date): static
    {
        return $this->state(fn () => ['planned_for' => $date]);
    }

    public function done(CloseReason $reason = CloseReason::COMPLETED): static
    {
        return $this->state(fn () => [
            'status' => DeliverableStatus::DONE,
            'close_reason' => $reason,
            'closed_at' => now(),
        ]);
    }

    /**
     * Still waiting in the backlog, out of every working screen.
     */
    public function inBacklog(): static
    {
        return $this->state(fn () => ['in_backlog' => true]);
    }
}
