<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\DeliverableEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliverableEvent>
 */
class DeliverableEventFactory extends Factory
{
    protected $model = DeliverableEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Deliverable::factory(),
            'event_type' => 'NOTE',
            'actor' => 'system',
        ];
    }
}
