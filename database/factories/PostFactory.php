<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state: a published post.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'slug' => Str::slug($title),
            'title_uz' => $title,
            'title_ru' => null,
            'body_uz' => fake()->paragraphs(2, true),
            'body_ru' => null,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => now()->addWeek()]);
    }
}
