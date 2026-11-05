<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ImportType::Publishers,
            'status' => ImportStatus::Succeeded,
            'countries' => ['UZ'],
            'created_count' => fake()->numberBetween(0, 50),
            'updated_count' => fake()->numberBetween(0, 50),
            'error' => null,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ImportStatus::Running, 'finished_at' => null]);
    }
}
