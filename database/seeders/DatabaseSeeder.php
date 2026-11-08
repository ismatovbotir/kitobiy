<?php

namespace Database\Seeders;

use App\Enums\ContributorRole;
use App\Enums\EditionSource;
use App\Enums\LibraryRole;
use App\Models\Author;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\LibraryInvitation;
use App\Models\Tag;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public const string DEMO_LIBRARY_SLUG = 'demo-home-library';

    /**
     * Seed a demo home library: an owner, a family member, tags
     * and 15 copies including one exact duplicate and one same-work duplicate.
     * Safe to re-run: it skips when the demo library already exists. It never
     * runs in production, because its accounts have a publicly known password.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Demo data is not seeded in production.');

            return;
        }

        if (Library::where('slug', self::DEMO_LIBRARY_SLUG)->exists()) {
            $this->command?->info('Demo library already seeded, skipping.');

            return;
        }

        $owner = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password'), 'confirmed_at' => now()],
        );

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            ['name' => 'Family Member', 'password' => Hash::make('password'), 'confirmed_at' => now()],
        );

        $library = Library::create([
            'name' => 'Our home library',
            'slug' => self::DEMO_LIBRARY_SLUG,
            'is_public' => false,
        ]);

        $library->members()->attach($owner, ['role' => LibraryRole::Owner->value]);
        $library->members()->attach($member, ['role' => LibraryRole::Member->value]);

        LibraryInvitation::factory()->for($library)->create([
            'invited_by' => $owner->id,
            'email' => 'grandma@example.com',
        ]);
        LibraryInvitation::factory()->for($library)->link()->create(['invited_by' => $owner->id]);

        $tags = collect(['Gift from grandmother', 'To read', 'Signed by the author'])
            ->mapWithKeys(fn (string $name): array => [
                $name => Tag::create(['library_id' => $library->id, 'name' => $name]),
            ]);

        $masterWork = Work::create(['original_title' => 'Мастер и Маргарита', 'original_language' => 'ru']);
        $littlePrinceWork = Work::create(['original_title' => 'Le Petit Prince', 'original_language' => 'fr']);
        $alchemistWork = Work::create(['original_title' => 'O Alquimista', 'original_language' => 'pt']);
        $crimeWork = Work::create(['original_title' => 'Преступление и наказание', 'original_language' => 'ru']);

        /**
         * @var list<array{title: string, author: string, language: string, genres: list<string>, tag?: string, copies?: int, isbn?: bool, work?: Work, translator?: string, open_library?: bool}> $books
         */
        $books = [
            ['title' => "O'tkan kunlar", 'author' => 'Abdulla Qodiriy', 'language' => 'uz', 'genres' => ['historical_fiction', 'prose'], 'tag' => 'Gift from grandmother', 'copies' => 2, 'isbn' => false],
            ['title' => 'Mehrobdan chayon', 'author' => 'Abdulla Qodiriy', 'language' => 'uz', 'genres' => ['prose'], 'isbn' => false],
            ['title' => 'Kecha va kunduz', 'author' => "Cho'lpon", 'language' => 'uz', 'genres' => ['prose'], 'isbn' => false],
            ['title' => 'Shum bola', 'author' => "G'afur G'ulom", 'language' => 'uz', 'genres' => ['children', 'prose']],
            ['title' => 'Sariq devni minib', 'author' => "Xudoyberdi To'xtaboyev", 'language' => 'uz', 'genres' => ['children']],
            ['title' => "Ulug'bek xazinasi", 'author' => 'Odil Yoqubov', 'language' => 'uz', 'genres' => ['historical_fiction']],
            ['title' => 'Мастер и Маргарита', 'author' => 'Михаил Булгаков', 'language' => 'ru', 'genres' => ['prose', 'fantasy'], 'work' => $masterWork],
            ['title' => 'Usta va Margarita', 'author' => 'Михаил Булгаков', 'language' => 'uz', 'genres' => ['prose', 'fantasy'], 'work' => $masterWork, 'translator' => 'Muhammad Ali'],
            ['title' => 'Kichkina shahzoda', 'author' => 'Antoine de Saint-Exupéry', 'language' => 'uz', 'genres' => ['children', 'philosophy'], 'tag' => 'Gift from grandmother', 'work' => $littlePrinceWork, 'translator' => 'Nodira Rahimova'],
            ['title' => 'Alkimyogar', 'author' => 'Paulo Coelho', 'language' => 'uz', 'genres' => ['prose', 'philosophy'], 'work' => $alchemistWork, 'translator' => 'Shahnoza Karimova'],
            ['title' => 'Преступление и наказание', 'author' => 'Фёдор Достоевский', 'language' => 'ru', 'genres' => ['prose'], 'work' => $crimeWork],
            ['title' => '1984', 'author' => 'George Orwell', 'language' => 'en', 'genres' => ['prose', 'fantasy'], 'tag' => 'To read', 'open_library' => true],
            ['title' => 'Sapiens', 'author' => 'Yuval Noah Harari', 'language' => 'en', 'genres' => ['history', 'science'], 'tag' => 'To read', 'open_library' => true],
            ['title' => 'Clean Code', 'author' => 'Robert C. Martin', 'language' => 'en', 'genres' => ['technology'], 'tag' => 'Signed by the author', 'open_library' => true],
        ];

        foreach ($books as $book) {
            $factory = Edition::factory();

            if (! ($book['isbn'] ?? true)) {
                $factory = $factory->withoutIsbn();
            }

            if ($book['open_library'] ?? false) {
                $factory = $factory->fromOpenLibrary();
            }

            $edition = $factory->create([
                'title' => $book['title'],
                'language' => $book['language'],
                'work_id' => isset($book['work']) ? $book['work']->id : null,
                'created_by' => $owner->id,
                'source' => ($book['open_library'] ?? false) ? EditionSource::OpenLibrary : EditionSource::Manual,
            ]);

            $edition->setPublisherName(match ($book['language']) {
                'uz' => 'G‘afur G‘ulom nashriyoti',
                'ru' => 'АСТ',
                default => 'Penguin Books',
            });
            $edition->save();

            $edition->authors()->attach(Author::firstOrCreate(['name' => $book['author']]), ['position' => 0]);

            if (isset($book['translator'])) {
                $edition->syncContributorNames(ContributorRole::Translator, $book['translator']);
            }

            Copy::factory()
                ->count($book['copies'] ?? 1)
                ->for($library)
                ->create(['edition_id' => $edition->id, 'added_by' => $owner->id])
                ->each(function (Copy $copy) use ($book, $tags): void {
                    if (isset($book['tag'])) {
                        $copy->tags()->attach($tags[$book['tag']]);
                    }
                });

            $edition->syncGenres($book['genres']);
        }
    }
}
