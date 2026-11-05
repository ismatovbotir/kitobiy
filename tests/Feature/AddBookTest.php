<?php

namespace Tests\Feature;

use App\Enums\EditionSource;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AddBookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->user = User::factory()->create();
        $this->library = Library::factory()->withMember($this->user)->create();
    }

    private function page(): Testable
    {
        return Livewire::actingAs($this->user)->test('pages::books.create');
    }

    public function test_page_renders_for_a_member(): void
    {
        $this->actingAs($this->user)->get(route('books.create'))->assertOk()->assertSee(__('Add a book'));
    }

    public function test_user_without_library_gets_their_default_library(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('books.create'))->assertOk();

        $this->assertTrue($user->currentLibrary()->isOwnedBy($user));
    }

    public function test_invalid_isbn_shows_an_error(): void
    {
        $this->page()
            ->set('isbn', '9780306406158')
            ->call('lookup')
            ->assertHasErrors('isbn')
            ->assertSet('step', 'search');
    }

    public function test_book_already_in_the_catalog_is_reused_without_calling_open_library(): void
    {
        $edition = Edition::factory()->create(['isbn_13' => '9780306406157']);

        $this->page()
            ->set('isbn', '0-306-40615-2')
            ->call('lookup')
            ->assertSet('editionId', $edition->id)
            ->assertSee($edition->title)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('step', 'search');

        $this->assertSame(1, Edition::count());
        $this->assertTrue($this->library->copies()->sole()->edition->is($edition));
        Http::assertNothingSent();
    }

    public function test_catalog_edition_stored_only_with_isbn_10_is_found_by_isbn_13(): void
    {
        $edition = Edition::factory()->create(['isbn_13' => null, 'isbn_10' => '0306406152']);

        $this->page()
            ->set('isbn', '9780306406157')
            ->call('lookup')
            ->assertSet('editionId', $edition->id);
    }

    public function test_owned_book_shows_a_duplicate_warning(): void
    {
        $edition = Edition::factory()->create(['isbn_13' => '9780306406157']);
        Copy::factory()->for($this->library)->for($edition)->create();

        $this->page()
            ->set('isbn', '9780306406157')
            ->call('lookup')
            ->assertSee(trans_choice('You already have this book (:count copy).|You already have this book (:count copies).', 1));
    }

    public function test_book_found_on_open_library_is_saved_into_the_catalog(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response([
            'ISBN:9780140449136' => [
                'key' => '/books/OL23140636M',
                'title' => 'Crime and punishment',
                'authors' => [['name' => 'Fyodor Dostoyevsky']],
                'publishers' => [['name' => 'Penguin']],
                'publish_date' => '2003',
                'number_of_pages' => 671,
            ],
        ])]);

        $this->page()
            ->set('isbn', '9780140449136')
            ->call('lookup')
            ->assertSet('title', 'Crime and punishment')
            ->assertSet('authors', 'Fyodor Dostoyevsky')
            ->set('language', 'en')
            ->call('save')
            ->assertHasNoErrors();

        $edition = Edition::sole();
        $this->assertSame(EditionSource::OpenLibrary, $edition->source);
        $this->assertSame('OL23140636M', $edition->source_id);
        $this->assertSame('0140449132', $edition->isbn_10);
        $this->assertSame('en', $edition->language);
        $this->assertSame(['Fyodor Dostoyevsky'], $edition->authors->pluck('name')->all());
        $this->assertSame('Penguin', $edition->publisher->name);
        $this->assertFalse($edition->publisher->is_verified);
        $this->assertTrue($edition->creator->is($this->user));
        $this->assertTrue($this->library->copies()->sole()->addedBy->is($this->user));
    }

    public function test_book_missing_on_open_library_is_found_on_google_books(): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response([]),
            'www.googleapis.com/books/*' => Http::response(['items' => [[
                'id' => 'zyTCAlFPjgYC',
                'volumeInfo' => [
                    'title' => 'O‘tkan kunlar',
                    'authors' => ['Abdulla Qodiriy'],
                    'publisher' => 'Sharq',
                    'publishedDate' => '2019-05-01',
                    'pageCount' => 400,
                    'language' => 'uz',
                    'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9780306406157']],
                ],
            ]]]),
        ]);

        $this->page()
            ->set('isbn', '9780306406157')
            ->call('lookup')
            ->assertSet('title', 'O‘tkan kunlar')
            ->assertSet('language', 'uz')
            ->assertSee(__('Found on :source. Check the details.', ['source' => 'Google Books']))
            ->call('save')
            ->assertHasNoErrors();

        $edition = Edition::sole();
        $this->assertSame(EditionSource::GoogleBooks, $edition->source);
        $this->assertSame('zyTCAlFPjgYC', $edition->source_id);
        $this->assertSame(2019, $edition->published_year);
        $this->assertSame('Sharq', $edition->publisher->name);
    }

    public function test_a_source_record_already_used_by_another_edition_is_not_stored_twice(): void
    {
        Edition::factory()->create(['source' => EditionSource::GoogleBooks, 'source_id' => 'zyTCAlFPjgYC']);

        Http::fake([
            'openlibrary.org/*' => Http::response([]),
            'www.googleapis.com/books/*' => Http::response(['items' => [[
                'id' => 'zyTCAlFPjgYC',
                'volumeInfo' => [
                    'title' => 'O‘tkan kunlar',
                    'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9780306406157']],
                ],
            ]]]),
        ]);

        $this->page()
            ->set('isbn', '9780306406157')
            ->call('lookup')
            ->call('save')
            ->assertHasNoErrors();

        $edition = Edition::firstWhere('isbn_13', '9780306406157');
        $this->assertSame(EditionSource::GoogleBooks, $edition->source);
        $this->assertNull($edition->source_id);
    }

    public function test_unknown_isbn_is_entered_by_hand(): void
    {
        Http::fake([
            'openlibrary.org/*' => Http::response([]),
            'www.googleapis.com/books/*' => Http::response(['totalItems' => 0]),
        ]);

        $this->page()
            ->set('isbn', '9789943000000')
            ->call('lookup')
            ->assertHasErrors('isbn');

        $this->page()
            ->set('isbn', '9780306406157')
            ->call('lookup')
            ->assertSet('step', 'details')
            ->assertSee(__('We could not find this ISBN. Please enter the details — the next reader will find it.'))
            ->set('title', 'O‘tkan kunlar')
            ->set('authors', 'Abdulla Qodiriy')
            ->set('language', 'uz')
            ->set('publishedYear', '2019')
            ->call('save')
            ->assertHasNoErrors();

        $edition = Edition::sole();
        $this->assertSame(EditionSource::Manual, $edition->source);
        $this->assertSame('9780306406157', $edition->isbn_13);
        $this->assertSame(2019, $edition->published_year);
    }

    public function test_any_world_language_can_be_chosen_but_not_an_unknown_code(): void
    {
        $this->page()
            ->call('addWithoutIsbn')
            ->set('title', 'Qoraqalpoq ertaklari')
            ->set('language', 'xx')
            ->call('save')
            ->assertHasErrors('language')
            ->set('language', 'kaa')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('kaa', Edition::sole()->language);
    }

    public function test_language_picker_is_shown_on_the_details_step(): void
    {
        $this->page()
            ->call('addWithoutIsbn')
            ->assertSee(__('Type at least 3 letters to search all languages.'))
            ->assertSeeHtml('role="combobox"');
    }

    public function test_book_without_isbn_needs_a_title(): void
    {
        $this->page()
            ->call('addWithoutIsbn')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);

        $this->page()
            ->call('addWithoutIsbn')
            ->set('title', 'Bobur noma')
            ->set('authors', 'Zahiriddin Muhammad Bobur, ')
            ->call('save')
            ->assertHasNoErrors();

        $edition = Edition::sole();
        $this->assertNull($edition->isbn_13);
        $this->assertSame(['Zahiriddin Muhammad Bobur'], $edition->authors->pluck('name')->all());
    }

    public function test_similar_titles_in_the_library_are_shown(): void
    {
        $edition = Edition::factory()->withoutIsbn()->create(['title' => 'Shum bola']);
        Copy::factory()->for($this->library)->for($edition)->create();

        $this->page()
            ->call('addWithoutIsbn')
            ->set('title', 'shum')
            ->assertSee(__('Similar books already in your library:'))
            ->assertSee('Shum bola');
    }

    public function test_new_tag_is_created_and_kept_for_the_next_book(): void
    {
        $component = $this->page()
            ->call('addWithoutIsbn')
            ->set('title', 'Book one')
            ->set('newTag', 'Bolalar')
            ->set('notes', 'Buvimning sovg‘asi')
            ->call('save')
            ->assertHasNoErrors();

        $tag = $this->library->tags()->sole();
        $copy = $this->library->copies()->sole();

        $this->assertTrue($copy->tags->sole()->is($tag));
        $this->assertSame('Buvimning sovg‘asi', $copy->notes);

        $component
            ->assertSet('tagIds', [(string) $tag->id])
            ->assertSet('notes', '');
    }

    public function test_tag_of_another_library_is_rejected(): void
    {
        $foreignTag = Tag::factory()->create();

        $this->page()
            ->call('addWithoutIsbn')
            ->set('title', 'Sneaky')
            ->set('tagIds', [(string) $foreignTag->id])
            ->call('save')
            ->assertHasErrors(['tagIds.0']);

        $this->assertSame(0, Copy::count());
    }

    public function test_locked_edition_id_cannot_be_tampered_with(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->page()->set('editionId', Edition::factory()->create()->id);
    }
}
