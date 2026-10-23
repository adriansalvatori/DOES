<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderElement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderElement>
 */
class OrderElementFactory extends Factory
{
    protected $model = OrderElement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_stage_id' => null,
            'name' => fake()->randomElement(['Pared Principal', 'Aviso Exterior 3D', 'Vidriera Frontal', 'Stickers Mesas', 'Señaletica Baños']),
            'dimensions' => fake()->randomElement(['3.5m x 2.4m', '1.2m x 0.8m', '24x36 in', null]),
            'material' => fake()->randomElement(['Vinilo mate', 'Acrílico 5mm', 'Lona frontlight', null]),
            'quantity' => fake()->numberBetween(1, 5),
            'sort_order' => fake()->numberBetween(0, 10),
            'status' => 'draft',
            'is_approved' => false,
            'approved_at' => null,
            'approval_type' => null,
            'approval_note' => null,
            'file_url' => null,
            'production_notes' => null,
            'completed_at' => null,
        ];
    }

    public function approved(string $type = 'cliente'): static
    {
        return $this->state(fn () => [
            'is_approved' => true,
            'approved_at' => now(),
            'approval_type' => $type,
            'status' => 'approved',
        ]);
    }

    public function inAlta(): static
    {
        return $this->state(fn () => [
            'is_approved' => true,
            'approved_at' => now(),
            'status' => 'in_alta',
        ]);
    }

    public function readyForProduction(): static
    {
        return $this->state(fn () => [
            'is_approved' => true,
            'approved_at' => now(),
            'status' => 'ready_for_production',
            'completed_at' => now(),
            'file_url' => 'https://drive.google.com/test',
        ]);
    }
}
