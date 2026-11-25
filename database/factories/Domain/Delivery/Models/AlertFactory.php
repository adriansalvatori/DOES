<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deliverable_id' => Deliverable::factory(),
            'kind' => AlertKind::CLIENT_FOLLOW_UP_1,
            'due_on' => now()->toDateString(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['resolved_at' => now()]);
    }
}
