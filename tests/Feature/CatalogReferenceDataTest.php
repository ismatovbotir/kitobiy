<?php

namespace Tests\Feature;

use App\Enums\ContributorRole;
use App\Models\Author;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogReferenceDataTest extends TestCase
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

    public function test_a_typed_publisher_name_reuses_a_known_publisher_by_name_or_alias(): void
    {
        $sharq = Publisher::factory()->verified()->create(['name' => 'Sharq']);
        $sharq->syncAliases([['name' => 'Шарқ', 'language' => 'uz-Cyrl']]);

        $this->assertTrue(Publisher::findOrCreateByName('  Sharq ')->is($sharq));
        $this->assertTrue(Publisher::findOrCreateByName('Шарқ')->is($sharq));
        $this->assertNull(Publisher::findOrCreateByName('   '));

        $new = Publisher::findOrCreateByName('Yangi asr avlodi');

        $this->assertFalse($new->is_verified);
        $this->assertSame(2, Publisher::count());
    }

    public function test_new_book_links_its_publisher_and_editing_can_change_it(): void
    {
        $sharq = Publisher::factory()->create(['name' => 'Sharq']);

        Livewire::actingAs($this->user)
            ->test('pages::books.create')
            ->call('addWithoutIsbn')
            ->set('title', 'Kecha va kunduz')
            ->set('publisher', 'Sharq')
            ->call('save')
            ->assertHasNoErrors();

        $edition = Edition::sole();
        $this->assertTrue($edition->publisher->is($sharq));

        Livewire::actingAs($this->user)
            ->test('pages::books.show', ['copy' => Copy::sole()])
            ->call('editEdition')
            ->assertSet('publisher', 'Sharq')
            ->set('publisher', '')
            ->call('saveEdition')
            ->assertHasNoErrors();

        $this->assertNull($edition->refresh()->publisher_id);
        $this->assertModelExists($sharq);
    }

    public function test_publisher_suggestions_match_aliases_with_prefix_and_verified_first(): void
    {
        Publisher::factory()->create(['name' => 'Yangi Sharq']);
        Publisher::factory()->create(['name' => 'Sharq press']);
        $sharq = Publisher::factory()->verified()->create(['name' => 'Sharq']);
        $sharq->syncAliases([['name' => 'Шарқ', 'language' => 'uz-Cyrl']]);

        $page = Livewire::actingAs($this->user)->test('pages::books.create')->instance();

        $this->assertSame(['Sharq', 'Sharq press', 'Yangi Sharq'], $page->suggestPublisherNames('Sharq'));
        $this->assertSame(['Sharq', 'Sharq press', 'Yangi Sharq'], $page->suggestPublisherNames('Шарқ'));
        $this->assertSame([], $page->suggestPublisherNames('S'));
    }

    public function test_a_typed_person_name_reuses_a_known_person_by_alias(): void
    {
        $qodiriy = Author::factory()->verified()->create(['name' => 'Abdulla Qodiriy']);
        $qodiriy->syncAliases([['name' => 'Абдулла Қодирий', 'language' => 'uz-Cyrl']]);
        $edition = Edition::factory()->create();

        $edition->syncContributorNames(ContributorRole::Author, 'Абдулла Қодирий');

        $this->assertSame([$qodiriy->id], $edition->authors()->pluck('authors.id')->all());
        $this->assertSame(1, Author::count());
    }

    public function test_a_name_typed_with_another_apostrophe_or_script_reuses_the_known_person(): void
    {
        $cholpon = Author::factory()->verified()->create(['name' => 'Choʻlpon']);
        $edition = Edition::factory()->create();

        $edition->syncContributorNames(ContributorRole::Author, "cho'lpon");
        $this->assertSame([$cholpon->id], $edition->authors()->pluck('authors.id')->all());

        $edition->syncContributorNames(ContributorRole::Author, 'Чўлпон');
        $this->assertSame([$cholpon->id], $edition->authors()->pluck('authors.id')->all());

        $this->assertSame(1, Author::count());
    }

    public function test_the_rebuild_command_refreshes_stale_search_keys(): void
    {
        $author = Author::factory()->create(['name' => 'Чўлпон']);
        $edition = Edition::factory()->create(['title' => 'Кеча ва кундуз']);
        Author::whereKey($author->id)->update(['search_name' => 'stale']);
        Edition::whereKey($edition->id)->update(['search_text' => 'stale']);

        $this->artisan('kitobiy:rebuild-search-keys')->assertSuccessful();

        $this->assertSame('cholpon', $author->fresh()->search_name);
        $this->assertSame('kecha va kunduz', $edition->fresh()->search_text);
    }

    public function test_a_verified_person_is_kept_when_removed_from_their_last_edition(): void
    {
        $verified = Author::factory()->verified()->create(['name' => 'Cho‘lpon']);
        $edition = Edition::factory()->create();
        $edition->syncContributorNames(ContributorRole::Author, 'Cho‘lpon, Typo Name');
        $typo = Author::where('name', 'Typo Name')->sole();

        $edition->syncContributorNames(ContributorRole::Author, '');

        $this->assertModelExists($verified);
        $this->assertModelMissing($typo);
    }

    public function test_name_suggestions_and_book_search_match_aliases(): void
    {
        $bulgakov = Author::factory()->create(['name' => 'Mikhail Bulgakov']);
        $bulgakov->syncAliases([['name' => 'Михаил Булгаков', 'language' => 'ru']]);
        $edition = Edition::factory()->create(['title' => 'Master i Margarita']);
        $edition->authors()->attach($bulgakov, ['position' => 0]);
        Copy::factory()->for($this->library)->for($edition)->create();
        Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Other book']))->create();

        $this->assertSame(
            ['Mikhail Bulgakov'],
            Livewire::actingAs($this->user)->test('pages::books.create')->instance()->suggestContributorNames('Булгаков'),
        );

        Livewire::actingAs($this->user)
            ->test('pages::books.index')
            ->set('search', 'Булгаков')
            ->assertSee('Master i Margarita')
            ->assertDontSee('Other book');
    }

    public function test_aliases_skip_blanks_duplicates_and_the_main_name(): void
    {
        $author = Author::factory()->create(['name' => 'Alisher Navoiy']);

        $author->syncAliases([
            ['name' => 'Алишер Навоий', 'language' => 'uz-Cyrl'],
            ['name' => 'алишер навоий'],
            ['name' => 'alisher navoiy'],
            ['name' => '  '],
        ]);

        $this->assertSame(['Алишер Навоий'], $author->aliases()->pluck('name')->all());
    }
}
