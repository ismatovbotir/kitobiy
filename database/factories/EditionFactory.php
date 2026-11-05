<?php

namespace Database\Factories;

use App\Enums\ContributorRole;
use App\Enums\EditionSource;
use App\Models\Edition;
use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Edition>
 */
class EditionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_id' => null,
            'title' => rtrim(fake()->sentence(fake()->numberBetween(1, 5)), '.'),
            'subtitle' => null,
            'language' => fake()->randomElement(['uz', 'ru', 'en']),
            'isbn_13' => fake()->unique()->isbn13(),
            'isbn_10' => null,
            'publisher_id' => null,
            'published_year' => fake()->numberBetween(1960, (int) date('Y')),
            'page_count' => fake()->numberBetween(80, 900),
            'cover_url' => null,
            'source' => EditionSource::Manual,
            'source_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * An edition without any ISBN, common for older or local Uzbek prints.
     */
    public function withoutIsbn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'isbn_13' => null,
            'isbn_10' => null,
        ]);
    }

    public function withIsbn10(): static
    {
        return $this->state(fn (array $attributes): array => [
            'isbn_10' => fake()->unique()->isbn10(),
        ]);
    }

    /**
     * Attach the edition to a work (new one by default).
     */
    public function ofWork(?Work $work = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'work_id' => $work ?? Work::factory(),
        ]);
    }

    /**
     * A translated edition of a work, with a translator.
     */
    public function translation(?Work $work = null, string $language = 'uz'): static
    {
        return $this->ofWork($work)
            ->state(fn (array $attributes): array => ['language' => $language])
            ->afterCreating(fn (Edition $edition) => $edition->syncContributorNames(ContributorRole::Translator, fake()->name()));
    }

    public function fromOpenLibrary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => EditionSource::OpenLibrary,
            'source_id' => 'OL'.fake()->unique()->numberBetween(1_000_000, 99_999_999).'M',
            'cover_url' => 'https://covers.openlibrary.org/b/isbn/'.$attributes['isbn_13'].'-L.jpg',
        ]);
    }
}
