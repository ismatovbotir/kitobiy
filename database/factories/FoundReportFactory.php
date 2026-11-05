<?php

namespace Database\Factories;

use App\Models\Copy;
use App\Models\FoundReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoundReport>
 */
class FoundReportFactory extends Factory
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
            'body' => fake()->sentence(),
            'finder_contact' => null,
        ];
    }
}
