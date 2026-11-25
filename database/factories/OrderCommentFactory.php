<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderComment>
 */
class OrderCommentFactory extends Factory
{
    protected $model = OrderComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    /**
     * A comment imported from a Trello card action.
     */
    public function fromTrello(?string $actionId = null): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'author_name' => fake()->name(),
            'trello_action_id' => $actionId ?? fake()->unique()->regexify('[a-f0-9]{24}'),
        ]);
    }
}
