<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'kind' => Media::KIND_FILE,
            'drive_file_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{33}'),
            'drive_folder_id' => fake()->regexify('[A-Za-z0-9_-]{33}'),
            'name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'source' => Media::SOURCE_UPLOAD,
        ];
    }

    /**
     * A link attachment (no Drive file).
     */
    public function link(): static
    {
        return $this->state(fn () => [
            'kind' => Media::KIND_LINK,
            'drive_file_id' => null,
            'drive_folder_id' => null,
            'external_url' => fake()->url(),
            'mime_type' => null,
            'size' => null,
        ]);
    }

    /**
     * A file imported from a Trello card attachment.
     */
    public function fromTrello(?string $attachmentId = null): static
    {
        return $this->state(fn () => [
            'source' => Media::SOURCE_TRELLO_IMPORT,
            'trello_attachment_id' => $attachmentId ?? fake()->unique()->regexify('[a-f0-9]{24}'),
        ]);
    }
}
