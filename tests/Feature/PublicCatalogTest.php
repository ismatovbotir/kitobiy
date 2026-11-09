<?php

namespace Tests\Feature;

use App\Enums\ContributorRole;
use App\Enums\EditionSource;
use App\Models\Author;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Genre;
use App\Models\Library;
use App\Models\Publisher;
use App\Models\User;
use App\Support\PublicCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A book from Open Library with an ISBN, held by one family.
     */
    private function publicBook(array $attributes = []): Edition
    {
        $edition = Edition::factory()->for(Publisher::factory()->verified()->state(['name' => 'Sharq']))->create([
            'title' => 'O‘tkan kunlar',
            'isbn_13' => '9789943000001',
            'source' => EditionSource::OpenLibrary,
            ...$attributes,
        ]);
        $edition->syncAuthorNames('Abdulla Qodiriy');
        $edition->syncContributorNames(ContributorRole::Translator, 'Mark Reese');

        return $edition;
    }

    public function test_guests_see_book_facts_but_nothing_about_the_families_who_own_it(): void
    {
        $edition = $this->publicBook();
        $owner = User::factory()->create(['name' => 'Aziz Karimov']);
        $library = Library::factory()->withMember($owner)->create(['name' => 'Karimovs’ shelf']);
        Copy::factory()->for($library)->for($edition)->create(['notes' => 'Gift from grandmother']);

        $this->get($edition->publicUrl())
            ->assertOk()
            ->assertSee('O‘tkan kunlar')
            ->assertSee('Abdulla Qodiriy')
            ->assertSee('Mark Reese')
            ->assertSee('Sharq')
            ->assertSee('9789943000001')
            ->assertSee('<link rel="canonical" href="'.$edition->publicUrl().'">', false)
            ->assertSee('"@type":"Book"', false)
            ->assertDontSee('noindex')
            ->assertDontSee('Aziz Karimov')
            ->assertDontSee('Karimovs')
            ->assertDontSee('Gift from grandmother');
    }

    public function test_an_old_or_missing_slug_redirects_to_the_page_address(): void
    {
        $edition = $this->publicBook();

        $this->assertStringEndsWith('/catalog/books/'.$edition->id.'/otkan-kunlar', $edition->publicUrl());
        $this->get(route('catalog.books.show', $edition))->assertRedirect($edition->publicUrl())->assertStatus(301);
        $this->get(route('catalog.books.show', [$edition, 'old-title']))->assertStatus(301);
    }

    public function test_a_familys_own_book_without_an_isbn_stays_private_until_several_libraries_have_it(): void
    {
        $edition = Edition::factory()->create(['title' => 'Bobomning xotiralari', 'isbn_13' => null, 'isbn_10' => null]);
        Copy::factory()->for($edition)->create();

        $this->get($edition->publicUrl())->assertNotFound();

        Copy::factory()->count(PublicCatalog::MIN_LIBRARIES_WITHOUT_ISBN - 1)->for($edition)->create();

        $this->get($edition->publicUrl())->assertOk()->assertDontSee('noindex');
    }

    public function test_a_typed_in_book_with_an_isbn_is_public_but_not_offered_to_search_engines(): void
    {
        $edition = Edition::factory()->create(['isbn_13' => '9789943000018', 'source' => EditionSource::Manual]);
        Copy::factory()->for($edition)->create();

        $this->get($edition->publicUrl())->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get(route('sitemap'))->assertOk()->assertDontSee($edition->publicUrl());
    }

    public function test_the_shelf_count_appears_only_from_several_libraries(): void
    {
        $edition = $this->publicBook();
        Copy::factory()->count(PublicCatalog::MIN_SHELVES_SHOWN - 1)->for($edition)->create();
        $count = fn (int $count): string => trans_choice('On the shelves of :count Kitobiy library.|On the shelves of :count Kitobiy libraries.', $count);

        $this->get($edition->publicUrl())->assertDontSee($count(PublicCatalog::MIN_SHELVES_SHOWN - 1));

        Copy::factory()->for($edition)->create();

        $this->get($edition->publicUrl())->assertSee($count(PublicCatalog::MIN_SHELVES_SHOWN));
    }

    public function test_super_admins_hide_a_book_and_show_it_again(): void
    {
        $edition = $this->publicBook();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs(User::factory()->create())->post(route('admin.catalog.visibility', ['books', $edition->id]))->assertForbidden();

        $this->actingAs($admin)->from($edition->publicUrl())->post(route('admin.catalog.visibility', ['books', $edition->id]))
            ->assertRedirect($edition->publicUrl());
        $this->assertTrue($edition->fresh()->hidden_from_catalog);

        $this->actingAs($admin)->get($edition->publicUrl())->assertOk()->assertSee(__('Show in the catalog'))->assertSee('noindex');
        auth()->logout();
        $this->get($edition->publicUrl())->assertNotFound();
        $this->get(route('catalog.people.show', $edition->authors->sole()))->assertNotFound();

        $this->actingAs($admin)->post(route('admin.catalog.visibility', ['books', $edition->id]));
        auth()->logout();
        $this->get($edition->publicUrl())->assertOk();
    }

    public function test_people_and_publishers_show_only_their_public_books(): void
    {
        $edition = $this->publicBook();
        $private = Edition::factory()->for($edition->publisher)->create(['title' => 'Oilaviy daftar', 'isbn_13' => null, 'isbn_10' => null]);
        $private->syncAuthorNames('Abdulla Qodiriy');
        $author = $edition->authors->sole();

        $this->get($author->publicUrl())
            ->assertOk()
            ->assertSee('O‘tkan kunlar')
            ->assertDontSee('Oilaviy daftar')
            ->assertSee('noindex');

        $author->forceFill(['is_verified' => true])->save();
        $this->get($author->publicUrl())->assertDontSee('noindex');

        $this->get($edition->publisher->publicUrl())
            ->assertOk()
            ->assertSee('O‘tkan kunlar')
            ->assertDontSee('Oilaviy daftar')
            ->assertSee(__('Add your books to Kitobiy'));

        $nobody = Author::factory()->create();
        $this->get($nobody->publicUrl())->assertNotFound();
    }

    public function test_a_publisher_that_keeps_its_own_list_says_so(): void
    {
        $publisher = Publisher::factory()->verified()->withMember(User::factory()->create())->create();
        Edition::factory()->for($publisher)->create(['source' => EditionSource::Publisher, 'isbn_13' => null]);

        $this->get($publisher->publicUrl())
            ->assertOk()
            ->assertSee(__('The publisher keeps this book list itself.'))
            ->assertDontSee(__('Add your books to Kitobiy'));
    }

    public function test_genre_pages_list_public_books(): void
    {
        $edition = $this->publicBook();
        $edition->syncGenres(['prose']);
        $prose = Genre::query()->where('slug', 'prose')->sole();

        $this->get(route('catalog.genres.show', $prose))->assertOk()->assertSee('O‘tkan kunlar');

        $prose->forceFill(['is_active' => false])->save();
        $this->get(route('catalog.genres.show', $prose))->assertNotFound();
    }

    public function test_the_catalog_finds_books_people_and_publishers(): void
    {
        $edition = $this->publicBook();

        $this->get(route('catalog.index'))->assertOk()->assertSee(__('Genres'))->assertSee('Sharq');
        $this->get(route('catalog.index', ['q' => 'otkan']))->assertOk()->assertSee('O‘tkan kunlar')->assertSee('noindex');
        $this->get(route('catalog.index', ['q' => '978-9943-000-00-1']))->assertSee('O‘tkan kunlar');
        $this->get(route('catalog.index', ['q' => 'Qodiriy']))->assertSee($edition->authors->sole()->publicUrl(), false);
        $this->get(route('catalog.index', ['q' => 'nothing like it']))->assertSee(__('Nothing found.'));
    }

    public function test_the_sitemap_lists_indexable_pages_only(): void
    {
        $edition = $this->publicBook();
        $verified = Author::factory()->create(['is_verified' => true]);
        $edition->syncAuthorNames('Abdulla Qodiriy, '.$verified->name);
        $hidden = $this->publicBook(['title' => 'Mehrobdan chayon', 'isbn_13' => '9789943000025', 'hidden_from_catalog' => true]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee($edition->publicUrl())
            ->assertSee($verified->publicUrl())
            ->assertSee($edition->publisher->publicUrl())
            ->assertSee(route('catalog.genres.show', 'prose'))
            ->assertDontSee($hidden->publicUrl())
            ->assertDontSee(Author::query()->where('name', 'Abdulla Qodiriy')->sole()->publicUrl());
    }
}
