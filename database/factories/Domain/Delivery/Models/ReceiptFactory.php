<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deliverable_id' => Deliverable::factory(),
            'method' => FulfillmentMethod::PICKUP,
            'received_at' => now(),
        ];
    }
}
