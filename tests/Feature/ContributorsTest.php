<?php

namespace Tests\Feature;

use App\Enums\ContributorRole;
use App\Models\Author;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContributorsTest extends TestCase
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

    public function test_new_book_is_saved_with_people_in_every_role(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::books.create')
            ->call('addWithoutIsbn')
            ->set('title', 'O‘zbek xalq ertaklari')
            ->set('compilers', 'Mansur Afzalov, Zubayda Husainova')
            ->set('editors', 'Hodi Zarif')
            ->set('illustrators', 'Rassom')
            ->set('translators', '')
            ->call('save')
            ->assertHasNoErrors();

        $edition = Edition::sole();

        $this->assertSame([], $edition->authors()->pluck('name')->all());
        $this->assertSame(['Mansur Afzalov', 'Zubayda Husainova'], $edition->contributorsWithRole(ContributorRole::Compiler)->pluck('name')->all());
        $this->assertSame(['Hodi Zarif'], $edition->contributorsWithRole(ContributorRole::Editor)->pluck('name')->all());
        $this->assertSame(['Rassom'], $edition->contributorsWithRole(ContributorRole::Illustrator)->pluck('name')->all());
    }

    public function test_book_page_shows_contributors_by_role_and_corrects_them(): void
    {
        $edition = Edition::factory()->create();
        $edition->syncContributorNames(ContributorRole::Author, 'Antoine de Saint-Exupéry');
        $edition->syncContributorNames(ContributorRole::Translator, 'Nodira Rahimova');
        $copy = Copy::factory()->for($this->library)->for($edition)->create();

        Livewire::actingAs($this->user)
            ->test('pages::books.show', ['copy' => $copy])
            ->assertSee(__('Translator'))
            ->assertSee('Nodira Rahimova')
            ->call('editEdition')
            ->assertSet('translators', 'Nodira Rahimova')
            ->set('translators', 'Nodira Rahimova, Ikkinchi Tarjimon')
            ->call('saveEdition')
            ->assertHasNoErrors();

        $this->assertSame(['Nodira Rahimova', 'Ikkinchi Tarjimon'], $edition->contributorsWithRole(ContributorRole::Translator)->pluck('name')->all());
        $this->assertSame(['Antoine de Saint-Exupéry'], $edition->authors()->pluck('name')->all());
    }

    public function test_name_suggestions_come_from_the_catalog_starting_matches_first(): void
    {
        Author::factory()->create(['name' => 'Abdulla Qodiriy']);
        Author::factory()->create(['name' => 'Abdulla Oripov']);
        Author::factory()->create(['name' => 'Said Ahmad Abdulla']);
        Author::factory()->create(['name' => 'Cho‘lpon']);

        $component = Livewire::actingAs($this->user)->test('pages::books.create');

        $this->assertSame(
            ['Abdulla Oripov', 'Abdulla Qodiriy', 'Said Ahmad Abdulla'],
            $component->instance()->suggestContributorNames('abdulla'),
        );
        $this->assertSame([], $component->instance()->suggestContributorNames('a'));
        $this->assertSame([], $component->instance()->suggestContributorNames('%%'));
    }

    public function test_search_finds_books_by_any_contributor(): void
    {
        $edition = Edition::factory()->create(['title' => 'Kichkina shahzoda']);
        $edition->syncContributorNames(ContributorRole::Translator, 'Nodira Rahimova');
        Copy::factory()->for($this->library)->for($edition)->create();
        Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Other book']))->create();

        Livewire::actingAs($this->user)
            ->test('pages::books.index')
            ->set('search', 'Rahimova')
            ->assertSee('Kichkina shahzoda')
            ->assertDontSee('Other book');
    }

    public function test_book_list_can_show_one_persons_books(): void
    {
        $qodiriy = Author::factory()->create(['name' => 'Abdulla Qodiriy']);
        $novel = Edition::factory()->create(['title' => 'O‘tkan kunlar']);
        $novel->authors()->attach($qodiriy, ['position' => 0]);
        $collection = Edition::factory()->create(['title' => 'Tanlangan asarlar']);
        $collection->contributorsWithRole(ContributorRole::Compiler)->attach($qodiriy, ['position' => 0]);
        Copy::factory()->for($this->library)->for($novel)->create();
        Copy::factory()->for($this->library)->for($collection)->create();
        Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Other book']))->create();

        Livewire::actingAs($this->user)
            ->withQueryParams(['person' => (string) $qodiriy->id])
            ->test('pages::books.index')
            ->assertSee(__('Books with :name', ['name' => 'Abdulla Qodiriy']))
            ->assertSee('O‘tkan kunlar')
            ->assertSee('Tanlangan asarlar')
            ->assertDontSee('Other book');
    }
}
