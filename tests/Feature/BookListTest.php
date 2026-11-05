<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class BookListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->library = Library::factory()->withMember($this->user)->create();
    }

    private function page(): Testable
    {
        return Livewire::actingAs($this->user)->test('pages::books.index');
    }

    private function copyOf(array $editionAttributes = [], array $copyAttributes = []): Copy
    {
        return Copy::factory()
            ->for($this->library)
            ->for(Edition::factory()->state($editionAttributes))
            ->create($copyAttributes);
    }

    public function test_page_lists_only_this_librarys_books(): void
    {
        $this->copyOf(['title' => 'O‘tkan kunlar']);
        Copy::factory()->for(Edition::factory()->state(['title' => 'Someone else’s book']))->create();

        $this->actingAs($this->user)->get(route('books.index'))
            ->assertOk()
            ->assertSee('O‘tkan kunlar')
            ->assertDontSee('Someone else’s book');
    }

    public function test_user_without_library_gets_their_default_library(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('books.index'))->assertOk();

        $this->assertTrue($user->currentLibrary()->isOwnedBy($user));
    }

    public function test_search_matches_title_author_and_isbn(): void
    {
        $byTitle = $this->copyOf(['title' => 'Mehrobdan chayon']);
        $byAuthor = $this->copyOf(['title' => 'Kecha va kunduz']);
        $byAuthor->edition->authors()->attach(Author::factory()->create(['name' => 'Cho‘lpon']), ['position' => 0]);
        $byIsbn = $this->copyOf(['title' => 'Sariq devni minib', 'isbn_13' => '9780306406157']);

        $this->page()->set('search', 'chayon')->assertSee('Mehrobdan chayon')->assertDontSee('Kecha va kunduz');
        $this->page()->set('search', 'Cho‘lpon')->assertSee('Kecha va kunduz')->assertDontSee('Mehrobdan chayon');
        $this->page()->set('search', '978-0-306-40615-7')->assertSee('Sariq devni minib')->assertDontSee('Kecha va kunduz');
    }

    public function test_search_ignores_apostrophes_case_and_latin_or_cyrillic(): void
    {
        $this->copyOf(['title' => 'O‘tkan kunlar']);
        $novel = $this->copyOf(['title' => 'Кеча ва кундуз', 'subtitle' => 'Роман']);
        $novel->edition->authors()->attach(Author::factory()->create(['name' => 'Choʻlpon']), ['position' => 0]);

        $this->page()->set('search', "o'tkan")->assertSee('O‘tkan kunlar')->assertDontSee('Кеча ва кундуз');
        $this->page()->set('search', 'ЎТКАН')->assertSee('O‘tkan kunlar');
        $this->page()->set('search', 'kecha va')->assertSee('Кеча ва кундуз')->assertDontSee('O‘tkan kunlar');
        $this->page()->set('search', 'roman')->assertSee('Кеча ва кундуз');
        $this->page()->set('search', 'Чўлпон')->assertSee('Кеча ва кундуз')->assertDontSee('O‘tkan kunlar');
        $this->page()->set('search', "cho'lpon")->assertSee('Кеча ва кундуз');
    }

    public function test_search_treats_percent_signs_literally(): void
    {
        $this->copyOf(['title' => 'Plain title']);

        $this->page()->set('search', '%')->assertDontSee('Plain title')->assertSee(__('No books match your search.'));
    }

    public function test_books_can_be_filtered_by_tag_and_language(): void
    {
        $tag = Tag::factory()->for($this->library)->create();

        $this->copyOf(['title' => 'Poetry book', 'language' => 'en'])->tags()->attach($tag);
        $this->copyOf(['title' => 'Loose book', 'language' => 'ru']);

        $this->page()->set('tag', (string) $tag->id)->assertSee('Poetry book')->assertDontSee('Loose book');
        $this->page()->set('language', 'ru')->assertSee('Loose book')->assertDontSee('Poetry book');
    }

    public function test_language_filter_offers_only_languages_in_this_library(): void
    {
        $this->copyOf(['title' => 'French book', 'language' => 'fr']);
        $this->copyOf(['title' => 'No language', 'language' => null]);
        Copy::factory()->for(Edition::factory()->state(['language' => 'de']))->create();

        $this->page()
            ->assertSeeHtml('value="fr"')
            ->assertDontSeeHtml('value="de"')
            ->set('language', 'none')
            ->assertSee('No language')
            ->assertDontSee('French book');
    }

    public function test_books_can_be_sorted_by_newest_or_title(): void
    {
        $this->copyOf(['title' => 'Alpomish']);
        $this->copyOf(['title' => 'Bahor']);

        $this->page()->assertSeeInOrder(['Bahor', 'Alpomish']);
        $this->page()->set('sort', 'title')->assertSeeInOrder(['Alpomish', 'Bahor']);
    }

    public function test_list_is_paginated(): void
    {
        Copy::factory()->count(30)->for($this->library)->create();

        $copies = $this->page()->instance()->copies;

        $this->assertSame(30, $copies->total());
        $this->assertCount(24, $copies->items());
    }
}
