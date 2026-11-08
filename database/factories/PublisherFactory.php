<?php

namespace Database\Factories;

use App\Models\Publisher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publisher>
 */
class PublisherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'country' => fake()->randomElement(['UZ', 'RU', 'KZ', null]),
            'city' => null,
            'website' => null,
            'wikidata_id' => null,
            'is_verified' => false,
        ];
    }

    /**
     * With a person who keeps its book list (approved), or who asked to (not approved).
     */
    public function withMember(User $user, bool $approved = true): static
    {
        return $this->afterCreating(fn (Publisher $publisher) => $publisher->members()->attach($user, [
            'position' => 'Editor',
            'phone' => '+998 90 123 45 67',
            'approved_at' => $approved ? now() : null,
        ]));
    }

    /**
     * A publisher checked by a super admin, as after a Wikidata import.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_verified' => true,
            'wikidata_id' => 'Q'.fake()->unique()->numberBetween(1_000, 99_999_999),
        ]);
    }
}
