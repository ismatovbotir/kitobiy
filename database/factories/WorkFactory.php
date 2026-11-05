<?php

namespace Database\Factories;

use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Work>
 */
class WorkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_title' => rtrim(fake()->sentence(fake()->numberBetween(1, 4)), '.'),
            'original_language' => fake()->randomElement(['uz', 'ru', 'en', 'de', 'fr', 'tr']),
        ];
    }
}
