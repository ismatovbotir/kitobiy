<?php

namespace Database\Factories;

use App\Enums\ListingKind;
use App\Models\Copy;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'copy_id' => Copy::factory(),
            'seller_id' => User::factory(),
            'title' => fake()->sentence(3),
            'kind' => ListingKind::Swap,
            'price' => null,
            'note' => null,
        ];
    }

    public function forSale(int $price = 50000): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ListingKind::Sell,
            'price' => $price,
        ]);
    }
}
