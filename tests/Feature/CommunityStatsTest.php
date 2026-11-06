<?php

namespace Tests\Feature;

use App\Models\Copy;
use App\Models\Edition;
use App\Models\User;
use App\Support\CommunityStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommunityStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_site_invites_the_first_readers_and_shows_no_small_numbers(): void
    {
        User::factory()->count(CommunityStats::MIN_READERS_TO_SHOW - 1)->create();

        $this->landingPage()
            ->assertOk()
            ->assertSee('The first page of the register')
            ->assertDontSee('The library register')
            ->assertDontSee('Home libraries');
    }

    public function test_totals_appear_once_there_are_enough_readers(): void
    {
        $owner = User::factory()->create();
        $library = $owner->createLibrary('Home');
        $edition = Edition::factory()->create();
        Copy::factory()->count(2)->for($library)->create(['edition_id' => $edition->id]);
        User::factory()->count(CommunityStats::MIN_READERS_TO_SHOW - 1)->create();

        $this->assertSame([
            'isReady' => true,
            'readers' => CommunityStats::MIN_READERS_TO_SHOW,
            'libraries' => 1,
            'books' => 2,
            'editions' => 1,
        ], array_intersect_key(CommunityStats::current(), array_flip(['isReady', 'readers', 'libraries', 'books', 'editions'])));

        $this->landingPage()
            ->assertSee('The library register')
            ->assertDontSee('The first page of the register');
    }

    public function test_unverified_and_suspended_people_are_not_counted(): void
    {
        User::factory()->create();
        User::factory()->unverified()->create();
        User::factory()->create()->suspend();

        $this->assertSame(1, CommunityStats::current()['readers']);
    }

    public function test_book_is_named_only_when_enough_different_readers_own_it(): void
    {
        $this->openTheRegister();
        $this->ownedByReaders(['title' => 'Popular one'], CommunityStats::MIN_READERS_PER_ROW + 1);
        $this->ownedByReaders(['title' => 'Popular two']);
        $this->ownedByReaders(['title' => 'Popular three']);
        $this->ownedByReaders(['title' => 'Rare book'], CommunityStats::MIN_READERS_PER_ROW - 1);

        $this->assertSame(
            ['Popular one', 'Popular three', 'Popular two'],
            collect(CommunityStats::current()['mostShelved'])->pluck('title')->sort()->values()->all(),
        );
        $this->assertSame('Popular one', CommunityStats::current()['mostShelved'][0]['title']);

        $this->landingPage()->assertSee('Popular one')->assertDontSee('Rare book');
    }

    public function test_one_person_with_many_libraries_cannot_put_a_title_on_the_public_page(): void
    {
        $this->openTheRegister();
        $this->ownedByReaders(['title' => 'Popular one']);
        $this->ownedByReaders(['title' => 'Popular two']);

        $owner = User::factory()->create();
        $edition = Edition::factory()->create(['title' => 'Advert for my shop']);

        foreach (range(1, User::MAX_OWNED_LIBRARIES) as $number) {
            Copy::factory()->for($owner->createLibrary("Library {$number}"))->create(['edition_id' => $edition->id]);
        }

        $this->landingPage()->assertDontSee('Advert for my shop');
    }

    public function test_books_hidden_from_the_gift_page_are_not_named(): void
    {
        $this->openTheRegister();
        $this->ownedByReaders(['title' => 'Popular one']);
        $this->ownedByReaders(['title' => 'Popular two']);
        $this->ownedByReaders(['title' => 'Secret book'], copy: ['hidden_from_gift_page' => true]);

        $this->landingPage()->assertDontSee('Secret book');
    }

    public function test_short_lists_stay_hidden(): void
    {
        $this->openTheRegister();
        $this->ownedByReaders(['title' => 'Popular one']);

        $this->landingPage()
            ->assertSee('The library register')
            ->assertDontSee('Most often on the shelves')
            ->assertDontSee('Popular one');
    }

    public function test_region_is_named_only_when_enough_readers_live_there(): void
    {
        $enough = CommunityStats::MIN_READERS_PER_ROW;

        $this->openTheRegister();
        User::factory()->count($enough)->create(['country_code' => 'UZ', 'region' => 'UZ-SA']);
        User::factory()->count($enough)->create(['country_code' => 'UZ', 'region' => 'UZ-BU']);
        User::factory()->count($enough)->create(['country_code' => 'UZ', 'region' => 'UZ-XO']);
        User::factory()->count($enough - 1)->create(['country_code' => 'UZ', 'region' => 'UZ-AN']);
        User::factory()->count($enough)->create(['country_code' => 'DE', 'region' => 'Bayern']);

        $this->assertEqualsCanonicalizing(['UZ-SA', 'UZ-BU', 'UZ-XO'], array_column(CommunityStats::current()['regions'], 'code'));

        $this->landingPage()
            ->assertSee(__('regions.UZ-SA'))
            ->assertDontSee(__('regions.UZ-AN'))
            ->assertDontSee('Bayern');
    }

    public function test_page_never_shows_a_person_or_a_library(): void
    {
        $this->openTheRegister();
        $owner = User::factory()->create(['name' => 'Zebiniso Karimova', 'email' => 'zebiniso@example.com', 'city' => 'Urgut', 'birth_date' => '1990-01-15']);
        $library = $owner->createLibrary('Karimovlar kutubxonasi');
        Copy::factory()->for($library)->create(['notes' => 'Private note']);

        $this->landingPage()
            ->assertDontSee('Zebiniso')
            ->assertDontSee('Karimov')
            ->assertDontSee('Urgut')
            ->assertDontSee('1990')
            ->assertDontSee('Private note');
    }

    public function test_numbers_are_kept_for_an_hour_so_visitors_do_not_load_the_database(): void
    {
        User::factory()->create();
        $this->assertSame(1, CommunityStats::current()['readers']);

        User::factory()->create();
        $this->assertSame(1, CommunityStats::current()['readers']);

        $this->travel(CommunityStats::CACHE_MINUTES + 1)->minutes();
        $this->assertSame(2, CommunityStats::current()['readers']);
    }

    private function openTheRegister(): void
    {
        User::factory()->count(CommunityStats::MIN_READERS_TO_SHOW)->create();
    }

    /**
     * @param  array<string, mixed>  $edition
     * @param  array<string, mixed>  $copy
     */
    private function ownedByReaders(array $edition, int $readers = CommunityStats::MIN_READERS_PER_ROW, array $copy = []): void
    {
        $book = Edition::factory()->create($edition);

        foreach (range(1, $readers) as $number) {
            Copy::factory()
                ->for(User::factory()->create()->createLibrary("Library {$number}"))
                ->create(['edition_id' => $book->id, ...$copy]);
        }
    }

    private function landingPage(): TestResponse
    {
        Cache::flush();
        $this->post(route('locale.update', 'en'));

        return $this->get(route('home'));
    }
}
