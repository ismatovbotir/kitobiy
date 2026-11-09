<?php

namespace Database\Factories;

use App\Models\BookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookEvent>
 */
class BookEventFactory extends Factory
{
    /**
     * Define the model's default state: a published event next week.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'slug' => Str::slug($title),
            'title_uz' => $title,
            'body_uz' => fake()->paragraph(),
            'starts_at' => now()->addWeek()->setTime(15, 0),
            'ends_at' => now()->addWeek()->setTime(17, 0),
            'place' => 'Alisher Navoiy nomidagi milliy kutubxona',
            'city' => 'Toshkent',
            'is_published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => false]);
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subMonth()->setTime(15, 0),
            'ends_at' => now()->subMonth()->setTime(17, 0),
        ]);
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes): array => ['place' => null, 'city' => null, 'online_url' => 'https://example.com/live']);
    }
}
