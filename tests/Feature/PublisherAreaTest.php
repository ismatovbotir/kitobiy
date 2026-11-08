<?php

namespace Tests\Feature;

use App\Enums\EditionSource;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Publisher;
use App\Models\PublisherMember;
use App\Models\User;
use App\Notifications\PublisherClaimApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class PublisherAreaTest extends TestCase
{
    use RefreshDatabase;

    private const string ISBN = '9789943000001';

    private function fillBook(Testable $page, string $isbn = self::ISBN): Testable
    {
        return $page
            ->set('isbn', $isbn)
            ->set('title', 'O‘tkan kunlar')
            ->set('authors', 'Abdulla Qodiriy')
            ->set('language', 'uz')
            ->set('publishedYear', '2024')
            ->set('pageCount', '400')
            ->set('genres', ['prose']);
    }

    public function test_a_person_asks_to_keep_a_publishers_list_and_the_admin_hears_of_it(): void
    {
        $this->withTelegramBot();
        config(['services.telegram.admin_id' => '777000111']);
        $user = User::factory()->create(['name' => 'Dilnoza']);
        $publisher = Publisher::factory()->create(['name' => 'Sharq']);

        Livewire::actingAs($user)->test('pages::publisher.index')
            ->set('publisher', 'Sharq')
            ->set('position', 'Editor')
            ->set('phone', '+998 90 123 45 67')
            ->call('claim')
            ->assertHasNoErrors()
            ->assertSee(__('Waiting for approval'));

        $member = PublisherMember::query()->sole();
        $this->assertTrue($member->publisher->is($publisher));
        $this->assertFalse($member->isApproved());
        Http::assertSent(fn (ClientRequest $request): bool => $request['chat_id'] === 777000111
            && str_contains($request['text'], 'Dilnoza')
            && str_contains($request['text'], route('admin.publishers.claims'))
            && ! str_contains($request['text'], '123 45 67'));

        $this->actingAs($user)->get(route('publisher.books', $publisher))->assertForbidden();
    }

    public function test_claims_are_limited(): void
    {
        $user = User::factory()->create();
        $page = Livewire::actingAs($user)->test('pages::publisher.index')->set('position', 'Editor')->set('phone', '+998901234567');

        $page->set('publisher', 'Sharq')->call('claim')->assertHasNoErrors();
        $page->set('position', 'Editor')->set('phone', '+998901234567')->set('publisher', 'Sharq')->call('claim')->assertHasErrors('publisher');

        foreach (['Noshir', 'Akademnashr'] as $name) {
            $page->set('position', 'Editor')->set('phone', '+998901234567')->set('publisher', $name)->call('claim')->assertHasNoErrors();
        }

        $page->set('position', 'Editor')->set('phone', '+998901234567')->set('publisher', 'Tafakkur')->call('claim')->assertHasErrors('publisher');
        $page->set('publisher', 'Yangi nashr')->set('phone', 'call me')->call('claim')->assertHasErrors('phone');

        $this->assertSame(PublisherMember::MAX_PENDING_CLAIMS, PublisherMember::count());
    }

    public function test_the_super_admin_approves_or_declines_claims(): void
    {
        Notification::fake();
        $admin = User::factory()->superAdmin()->create();
        $person = User::factory()->create();
        $other = User::factory()->create();
        $publisher = Publisher::factory()->withMember($person, approved: false)->withMember($other, approved: false)->create();

        $this->actingAs($person)->get(route('admin.publishers.claims'))->assertForbidden();

        $page = Livewire::actingAs($admin)->test('pages::admin.publishers.claims')
            ->assertSee($person->name)
            ->assertSee('+998 90 123 45 67');

        $page->call('approve', $publisher->members()->whereKey($person->id)->sole()->membership->id);
        $page->call('remove', $publisher->members()->whereKey($other->id)->sole()->membership->id);

        $this->assertTrue($publisher->isManagedBy($person));
        $this->assertTrue($publisher->fresh()->is_verified);
        $this->assertFalse($publisher->members()->whereKey($other->id)->exists());
        Notification::assertSentTo($person, PublisherClaimApproved::class);
        Notification::assertNotSentTo($other, PublisherClaimApproved::class);

        $this->actingAs($person)->get(route('publisher.books', $publisher))->assertOk();
    }

    public function test_a_publisher_adds_a_book_that_readers_then_find_by_scanning(): void
    {
        $user = User::factory()->create();
        $publisher = Publisher::factory()->withMember($user)->create();

        $this->fillBook(Livewire::actingAs($user)->test('pages::publisher.book', ['publisher' => $publisher]))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('publisher.books', $publisher));

        $edition = Edition::query()->sole();
        $this->assertSame(EditionSource::Publisher, $edition->source);
        $this->assertTrue($edition->publisher->is($publisher));
        $this->assertSame('Abdulla Qodiriy', $edition->authors->sole()->name);
        $this->assertSame(['prose'], $edition->genres->pluck('slug')->all());

        $reader = User::factory()->create();
        $library = Library::factory()->withMember($reader)->create();
        Http::preventStrayRequests();

        Livewire::actingAs($reader)->test('pages::books.create')
            ->set('quickAdd', true)
            ->set('isbn', self::ISBN)
            ->call('lookup')
            ->assertDispatched('book-added-quickly');

        $this->assertTrue($library->copies()->sole()->edition->is($edition));
    }

    public function test_a_book_readers_entered_moves_to_the_publisher_with_its_copies(): void
    {
        $user = User::factory()->create();
        $publisher = Publisher::factory()->withMember($user)->create();
        $readerEdition = Edition::factory()->create(['isbn_13' => self::ISBN, 'title' => 'Otgan kunlar', 'source' => EditionSource::OpenLibrary, 'source_id' => 'OL1M']);
        $copy = Copy::factory()->for($readerEdition)->create();

        $this->fillBook(Livewire::actingAs($user)->test('pages::publisher.book', ['publisher' => $publisher]))
            ->call('save')
            ->assertHasNoErrors();

        $readerEdition->refresh();
        $this->assertSame(1, Edition::count());
        $this->assertSame('O‘tkan kunlar', $readerEdition->title);
        $this->assertSame(EditionSource::Publisher, $readerEdition->source);
        $this->assertNull($readerEdition->source_id);
        $this->assertTrue($readerEdition->publisher->is($publisher));
        $this->assertTrue($copy->fresh()->edition->is($readerEdition));
    }

    public function test_an_isbn_of_another_publishers_book_is_not_taken(): void
    {
        $user = User::factory()->create();
        $publisher = Publisher::factory()->withMember($user)->create();
        $rival = Publisher::factory()->withMember(User::factory()->create())->create();
        Edition::factory()->for($publisher)->create(['isbn_13' => '9789943000018', 'source' => EditionSource::Publisher]);
        Edition::factory()->for($rival)->create(['isbn_13' => '9789943000025', 'source' => EditionSource::Publisher]);
        Edition::factory()->for($rival)->create(['isbn_13' => '9789943000032']);

        foreach (['9789943000018', '9789943000025', '9789943000032', '9789943000033'] as $isbn) {
            $this->fillBook(Livewire::actingAs($user)->test('pages::publisher.book', ['publisher' => $publisher]), $isbn)
                ->call('save')
                ->assertHasErrors('isbn');
        }

        $this->assertSame(1, $publisher->editions()->count());
    }

    public function test_a_publisher_edits_only_its_own_books(): void
    {
        $user = User::factory()->create();
        $publisher = Publisher::factory()->withMember($user)->create();
        $edition = Edition::factory()->for($publisher)->create(['isbn_13' => self::ISBN, 'source' => EditionSource::Publisher]);
        $foreign = Edition::factory()->for(Publisher::factory())->create();

        Livewire::actingAs($user)->test('pages::publisher.book', ['publisher' => $publisher, 'edition' => $edition])
            ->assertSet('isbn', self::ISBN)
            ->set('title', 'New title')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New title', $edition->fresh()->title);
        $this->actingAs($user)->get(route('publisher.books.edit', [$publisher, $foreign]))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('publisher.books.edit', [$publisher, $edition]))->assertForbidden();
    }

    public function test_a_removed_member_loses_access_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $publisher = Publisher::factory()->withMember($user)->create();
        $page = Livewire::actingAs($user)->test('pages::publisher.books', ['publisher' => $publisher]);

        PublisherMember::query()->delete();

        $page->set('search', 'x')->assertForbidden();
    }

    public function test_readers_cannot_change_a_publishers_book(): void
    {
        $reader = User::factory()->create();
        $library = Library::factory()->withMember($reader)->create();
        $edition = Edition::factory()->for(Publisher::factory())->create(['source' => EditionSource::Publisher, 'cover_url' => 'https://example.com/c.jpg']);
        $copy = Copy::factory()->for($library)->for($edition)->create();
        $edition->syncGenres(['prose']);

        $this->assertFalse($reader->can('updateEdition', $copy));
        $this->assertFalse($reader->can('updateGenres', $copy));
        $this->assertFalse($reader->can('updateCover', $copy));

        $this->actingAs($reader)->get(route('books.show', $copy))->assertOk()->assertSee(__('Details from the publisher.'));
    }

    public function test_merging_publishers_keeps_their_members(): void
    {
        $user = User::factory()->create();
        $both = User::factory()->create();
        $duplicate = Publisher::factory()->withMember($user)->withMember($both)->create();
        $target = Publisher::factory()->withMember($both, approved: false)->create();

        $duplicate->mergeInto($target);

        $this->assertTrue($target->isManagedBy($user));
        $this->assertSame(2, $target->members()->count());
    }
}
