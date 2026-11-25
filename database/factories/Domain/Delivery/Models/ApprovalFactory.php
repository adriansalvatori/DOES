<?php

namespace Database\Factories\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
{
    protected $model = Approval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deliverable_id' => Deliverable::factory(),
            'source' => ApprovalSource::REVIEWER,
            'measurements_confirmed' => false,
            'estimate_approved' => false,
            'approved_at' => now(),
        ];
    }

    public function fromClient(bool $measurementsConfirmed = true, bool $estimateApproved = true): static
    {
        return $this->state(fn () => [
            'source' => ApprovalSource::CLIENT,
            'measurements_confirmed' => $measurementsConfirmed,
            'estimate_approved' => $estimateApproved,
        ]);
    }

    public function reset(): static
    {
        return $this->state(fn () => ['reset_at' => now()]);
    }
}
