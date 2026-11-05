<?php

namespace Database\Factories;

use App\Models\CollectorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CollectorProfile>
 */
class CollectorProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->firstName(),
            'bio' => null,
        ];
    }

    /**
     * A profile that is turned on.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes): array => [
            'share_token' => Str::random(CollectorProfile::SHARE_TOKEN_LENGTH),
        ]);
    }
}
