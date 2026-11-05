<?php

namespace Database\Factories;

use App\Models\Library;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
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
            'title' => fake()->unique()->sentence(3),
            'author' => fake()->name(),
        ];
    }
}
