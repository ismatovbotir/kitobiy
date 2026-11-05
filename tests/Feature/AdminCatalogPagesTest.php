<?php

namespace Tests\Feature;

use App\Enums\ContributorRole;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Genre;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCatalogPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_catalog_pages_are_for_super_admins_only(): void
    {
        $user = User::factory()->create();
        $author = Author::factory()->create();

        foreach ([route('admin.authors.index'), route('admin.authors.edit', $author), route('admin.publishers.index'), route('admin.publishers.create'), route('admin.genres.index')] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        Livewire::actingAs($user)->test('pages::admin.genres.index')->assertForbidden();
    }

    public function test_people_list_searches_aliases_and_filters_unverified(): void
    {
        $qodiriy = Author::factory()->verified()->create(['name' => 'Abdulla Qodiriy']);
        $qodiriy->syncAliases([['name' => 'Абдулла Қодирий', 'language' => 'uz-Cyrl']]);
        Author::factory()->create(['name' => 'Typo Person']);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.authors.index')
            ->set('search', 'Қодирий')
            ->assertSee('Abdulla Qodiriy')
            ->assertDontSee('Typo Person')
            ->set('search', '')
            ->set('status', 'unverified')
            ->assertSee('Typo Person')
            ->assertDontSee('Abdulla Qodiriy');
    }

    public function test_super_admin_edits_a_person_with_aliases(): void
    {
        $author = Author::factory()->create(['name' => 'Cholpon']);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.authors.edit', ['author' => $author])
            ->set('name', 'Abdulhamid Cho‘lpon')
            ->set('birthYear', 1897)
            ->set('deathYear', 1938)
            ->set('country', 'uz')
            ->set('wikidataId', 'q1373095')
            ->set('isVerified', true)
            ->call('addAlias')
            ->set('aliases.0.name', 'Чўлпон')
            ->set('aliases.0.language', 'uz-Cyrl')
            ->call('save')
            ->assertHasNoErrors();

        $author->refresh();
        $this->assertSame('Abdulhamid Cho‘lpon', $author->name);
        $this->assertSame([1897, 1938, 'UZ', 'Q1373095', true], [$author->birth_year, $author->death_year, $author->country, $author->wikidata_id, $author->is_verified]);
        $this->assertSame(['Чўлпон'], $author->aliases()->pluck('name')->all());
    }

    public function test_person_validation_rejects_bad_years_and_a_taken_wikidata_id(): void
    {
        Author::factory()->create(['wikidata_id' => 'Q42']);
        $author = Author::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.authors.edit', ['author' => $author])
            ->set('birthYear', 1950)
            ->set('deathYear', 1900)
            ->set('wikidataId', 'Q42')
            ->set('country', 'Uzbekistan')
            ->call('save')
            ->assertHasErrors(['deathYear', 'wikidataId', 'country']);
    }

    public function test_merging_two_people_moves_books_and_names_and_deletes_the_duplicate(): void
    {
        $real = Author::factory()->verified()->create(['name' => 'Abdulla Qodiriy', 'birth_year' => null]);
        $duplicate = Author::factory()->create(['name' => 'A. Qodiriy', 'birth_year' => 1894]);
        $duplicate->syncAliases([['name' => 'Абдулла Қодирий', 'language' => 'uz-Cyrl']]);

        $onlyDuplicate = Edition::factory()->create();
        $onlyDuplicate->syncContributorNames(ContributorRole::Author, 'A. Qodiriy');
        $both = Edition::factory()->create();
        $both->syncContributorNames(ContributorRole::Author, 'Abdulla Qodiriy, A. Qodiriy');

        Livewire::actingAs($this->admin)
            ->test('pages::admin.authors.edit', ['author' => $duplicate])
            ->set('mergeSearch', 'Abdulla')
            ->assertSee('Abdulla Qodiriy')
            ->call('mergeInto', $real->id)
            ->assertRedirect(route('admin.authors.edit', $real));

        $this->assertModelMissing($duplicate);
        $real->refresh();
        $this->assertSame(1894, $real->birth_year);
        $this->assertEqualsCanonicalizing(['A. Qodiriy', 'Абдулла Қодирий'], $real->aliases()->pluck('name')->all());
        $this->assertSame(['Abdulla Qodiriy'], $onlyDuplicate->authors()->pluck('name')->all());
        $this->assertSame(['Abdulla Qodiriy'], $both->authors()->pluck('name')->all());
    }

    public function test_only_an_unused_person_can_be_deleted(): void
    {
        $used = Author::factory()->create(['name' => 'Used Person']);
        Edition::factory()->create()->syncContributorNames(ContributorRole::Author, 'Used Person');
        $unused = Author::factory()->verified()->create();

        Livewire::actingAs($this->admin)->test('pages::admin.authors.edit', ['author' => $used])->call('delete')->assertForbidden();
        Livewire::actingAs($this->admin)->test('pages::admin.authors.edit', ['author' => $unused])->call('delete')->assertRedirect(route('admin.authors.index'));

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_super_admin_creates_verifies_and_merges_publishers(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.publishers.edit')
            ->set('name', 'Sharq')
            ->set('city', 'Toshkent')
            ->set('country', 'uz')
            ->set('website', 'https://sharq.uz')
            ->call('addAlias')
            ->set('aliases.0.name', 'Шарқ')
            ->call('save')
            ->assertHasNoErrors();

        $sharq = Publisher::where('name', 'Sharq')->sole();
        $this->assertTrue($sharq->is_verified);
        $this->assertSame(['Toshkent', 'UZ'], [$sharq->city, $sharq->country]);

        $typo = Publisher::factory()->create(['name' => 'Sharq nashriyoti']);
        $edition = Edition::factory()->create(['publisher_id' => $typo->id]);

        Livewire::actingAs($this->admin)->test('pages::admin.publishers.index')->call('verify', $typo->id);
        $this->assertTrue($typo->refresh()->is_verified);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.publishers.edit', ['publisher' => $typo])
            ->call('mergeInto', $sharq->id)
            ->assertRedirect(route('admin.publishers.edit', $sharq));

        $this->assertModelMissing($typo);
        $this->assertSame($sharq->id, $edition->refresh()->publisher_id);
        $this->assertEqualsCanonicalizing(['Шарқ', 'Sharq nashriyoti'], $sharq->aliases()->pluck('name')->all());
    }

    public function test_super_admin_adds_edits_hides_and_deletes_genres(): void
    {
        $page = Livewire::actingAs($this->admin)
            ->test('pages::admin.genres.index')
            ->set('slug', 'comics')
            ->set('nameUz', 'Komikslar')
            ->set('nameRu', 'Комиксы')
            ->set('nameEn', 'Comics')
            ->call('save')
            ->assertHasNoErrors();

        $comics = Genre::where('slug', 'comics')->sole();
        $this->assertSame(23, $comics->position);

        $page->call('edit', $comics->id)->set('nameEn', 'Comics & graphic novels')->call('save');
        $this->assertSame('Comics & graphic novels', $comics->refresh()->name_en);
        $this->assertSame('comics', $comics->slug);

        $page->call('toggleActive', $comics->id);
        $this->assertFalse($comics->refresh()->is_active);

        $page->call('delete', $comics->id);
        $this->assertModelMissing($comics);
    }

    public function test_a_genre_in_use_cannot_be_deleted_and_slugs_must_be_unique(): void
    {
        Edition::factory()->create()->syncGenres(['poetry']);
        $poetry = Genre::where('slug', 'poetry')->sole();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.genres.index')
            ->call('delete', $poetry->id)
            ->assertForbidden();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.genres.index')
            ->set('slug', 'poetry')
            ->set('nameUz', 'X')
            ->set('nameRu', 'X')
            ->set('nameEn', 'X')
            ->call('save')
            ->assertHasErrors(['slug']);

        $this->assertModelExists($poetry);
    }

    public function test_dragging_a_genre_reorders_the_list(): void
    {
        $history = Genre::where('slug', 'history')->sole();

        Livewire::actingAs($this->admin)->test('pages::admin.genres.index')->call('sortGenre', $history->id, 0);

        $this->assertSame('history', Genre::query()->ordered()->first()->slug);
        $this->assertSame(range(1, 22), Genre::query()->ordered()->pluck('position')->all());
    }
}
