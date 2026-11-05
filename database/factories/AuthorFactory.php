<?php

namespace Database\Factories;

use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'birth_year' => null,
            'death_year' => null,
            'country' => null,
            'wikidata_id' => null,
            'is_verified' => false,
        ];
    }

    /**
     * A person checked by a super admin, as after a Wikidata import.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_verified' => true,
            'wikidata_id' => 'Q'.fake()->unique()->numberBetween(1_000, 99_999_999),
        ]);
    }
}
