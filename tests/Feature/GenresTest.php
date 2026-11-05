<?php

namespace Tests\Feature;

use App\Models\Copy;
use App\Models\Edition;
use App\Models\Genre;
use App\Models\Library;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class GenresTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->library = Library::factory()->ownedBy($this->user)->create();
    }

    public function test_the_starting_genres_are_inserted_with_names_in_every_locale(): void
    {
        $this->assertSame(22, Genre::count());
        $this->assertSame('prose', Genre::query()->ordered()->first()->slug);

        $poetry = Genre::where('slug', 'poetry')->sole();

        app()->setLocale('ru');
        $this->assertSame('Поэзия', $poetry->name);
        app()->setLocale('uz');
        $this->assertSame('She’riyat', $poetry->name);
    }

    public function test_sync_genres_keeps_at_most_three_known_genres_in_display_order(): void
    {
        $edition = Edition::factory()->create();

        $edition->syncGenres(['history', 'unknown', 'prose', 'history', 'poetry', 'science']);

        $this->assertSame(['prose', 'poetry', 'history'], $this->slugs($edition));

        $edition->syncGenres(['children']);

        $this->assertSame(['children'], $this->slugs($edition));
        $this->assertDatabaseCount('edition_genre', 1);
    }

    public function test_an_inactive_genre_cannot_be_newly_picked_but_stays_on_editions_that_have_it(): void
    {
        $edition = Edition::factory()->create();
        $edition->syncGenres(['poetry', 'drama']);
        Genre::where('slug', 'poetry')->update(['is_active' => false]);
        Genre::where('slug', 'history')->update(['is_active' => false]);

        $edition->syncGenres(['poetry', 'history', 'children']);

        $this->assertSame(['poetry', 'children'], $this->slugs($edition));
        $this->assertFalse(Genre::pickable()->contains('slug', 'poetry'));
        $this->assertTrue(Genre::pickable(['poetry'])->contains('slug', 'poetry'));
    }

    public function test_a_genre_in_use_cannot_be_deleted(): void
    {
        Edition::factory()->create()->syncGenres(['poetry']);

        $this->expectException(QueryException::class);

        Genre::where('slug', 'poetry')->delete();
    }

    public function test_new_book_is_saved_with_its_genres(): void
    {
        $this->addBookPage()
            ->call('addWithoutIsbn')
            ->set('title', 'O‘tkan kunlar')
            ->set('genres', ['historical_fiction', 'prose'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['prose', 'historical_fiction'], $this->slugs(Edition::sole()));
    }

    public function test_more_than_three_genres_or_unknown_genre_are_rejected(): void
    {
        $this->addBookPage()
            ->call('addWithoutIsbn')
            ->set('title', 'Too many')
            ->set('genres', ['prose', 'poetry', 'drama', 'history'])
            ->call('save')
            ->assertHasErrors('genres')
            ->set('genres', ['cookbooks'])
            ->call('save')
            ->assertHasErrors('genres.0');

        $this->assertSame(0, Copy::count());
    }

    public function test_adding_a_copy_does_not_overwrite_existing_catalog_genres(): void
    {
        $edition = Edition::factory()->create(['isbn_13' => '9780306406157']);
        $edition->syncGenres(['poetry']);

        $this->addBookPage()
            ->set('isbn', '9780306406157')
            ->call('lookup')
            ->assertSet('editionId', $edition->id)
            ->set('genres', ['technology'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['poetry'], $this->slugs($edition));
    }

    public function test_genres_of_a_shared_edition_can_be_set_once_but_not_changed(): void
    {
        $edition = Edition::factory()->create();
        $copy = Copy::factory()->for($this->library)->for($edition)->create();
        Copy::factory()->for($edition)->create();

        $this->copyPage($copy)->set('genres', ['poetry'])->call('saveGenres')->assertHasNoErrors();
        $this->assertSame(['poetry'], $this->slugs($edition));

        $this->copyPage($copy)->set('genres', ['drama'])->call('saveGenres')->assertForbidden();
        $this->assertSame(['poetry'], $this->slugs($edition));
    }

    public function test_genres_of_an_edition_held_only_by_this_library_can_be_changed(): void
    {
        $edition = Edition::factory()->create();
        $edition->syncGenres(['poetry']);
        $copy = Copy::factory()->for($this->library)->for($edition)->create();

        $this->copyPage($copy)
            ->assertSet('genres', ['poetry'])
            ->set('genres', ['drama', 'children'])
            ->call('saveGenres')
            ->assertHasNoErrors();

        $this->assertSame(['drama', 'children'], $this->slugs($edition));
    }

    public function test_book_list_can_be_filtered_by_genre(): void
    {
        $poems = Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Poems book']))->create();
        $poems->edition->syncGenres(['poetry']);
        Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'History book']))->create()
            ->edition->syncGenres(['history']);

        Livewire::actingAs($this->user)
            ->test('pages::books.index')
            ->assertSeeHtml('value="poetry"')
            ->assertDontSeeHtml('value="drama"')
            ->set('genre', 'poetry')
            ->assertSee('Poems book')
            ->assertDontSee('History book');
    }

    /**
     * @return list<string>
     */
    private function slugs(Edition $edition): array
    {
        return $edition->refresh()->genres->pluck('slug')->all();
    }

    private function addBookPage(): Testable
    {
        return Livewire::actingAs($this->user)->test('pages::books.create');
    }

    private function copyPage(Copy $copy): Testable
    {
        return Livewire::actingAs($this->user)->test('pages::books.show', ['copy' => $copy]);
    }
}
