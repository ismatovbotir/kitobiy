<?php

namespace Database\Factories;

use App\Models\LabelTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabelTemplate>
 */
class LabelTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'width_mm' => 50,
            'height_mm' => 30,
            'elements' => [
                ['type' => 'title', 'x' => 2, 'y' => 2, 'w' => 30, 'h' => 8, 'font_size' => 10, 'bold' => true, 'align' => 'left', 'text' => null],
                ['type' => 'qr_code', 'x' => 34, 'y' => 2, 'w' => 14, 'h' => 14, 'font_size' => 8, 'bold' => false, 'align' => 'left', 'text' => null],
            ],
            'position' => fake()->numberBetween(100, 999),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
