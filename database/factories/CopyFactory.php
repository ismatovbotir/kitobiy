<?php

namespace Database\Factories;

use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Copy>
 */
class CopyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'edition_id' => Edition::factory(),
            'added_by' => null,
            'notes' => null,
        ];
    }

    public function hiddenFromGiftPage(): static
    {
        return $this->state(fn (array $attributes): array => [
            'hidden_from_gift_page' => true,
        ]);
    }

    public function withNotes(): static
    {
        return $this->state(fn (array $attributes): array => [
            'notes' => fake()->randomElement(['Gift from grandmother', 'Signed by the author', 'Cover slightly torn', 'Bought at the book fair']),
        ]);
    }
}
