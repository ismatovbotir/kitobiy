<?php

namespace Tests\Feature;

use App\Enums\CopyStatus;
use App\Enums\LibraryRole;
use App\Enums\ListingKind;
use App\Enums\ListingStatus;
use App\Models\CollectorProfile;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $visitor;

    private Library $library;

    private Copy $copy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create(['name' => 'Karim Olimov', 'email' => 'karim@example.com']);
        CollectorProfile::factory()->for($this->seller)->create(['display_name' => 'Kitobxon']);
        $this->library = Library::factory()->ownedBy($this->seller)->create(['name' => 'Olimovlar uyi']);
        $this->copy = Copy::factory()
            ->for($this->library)
            ->for(Edition::factory()->create(['title' => 'Kecha va kunduz']))
            ->create(['notes' => 'Private note']);

        $this->visitor = User::factory()->create();
        CollectorProfile::factory()->for($this->visitor)->create();
    }

    private function listing(): Listing
    {
        return $this->copy->createListing($this->seller, ListingKind::Sell, 50000, null);
    }

    public function test_guest_cannot_see_the_market(): void
    {
        $this->get(route('market.index'))->assertRedirect(route('login'));
        $this->get(route('market.show', $this->listing()))->assertRedirect(route('login'));
    }

    public function test_member_offers_a_book_for_sale(): void
    {
        Livewire::actingAs($this->seller)
            ->test('pages::market.create', ['copy' => $this->copy])
            ->set('kind', 'sell')
            ->set('price', '50000')
            ->set('note', 'Good copy')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $listing = $this->copy->listings()->sole();
        $this->assertSame('Kecha va kunduz', $listing->title);
        $this->assertSame(50000, $listing->price);
        $this->assertSame($this->seller->id, $listing->seller_id);
    }

    public function test_sale_needs_a_price_and_swap_never_keeps_one(): void
    {
        Livewire::actingAs($this->seller)
            ->test('pages::market.create', ['copy' => $this->copy])
            ->set('kind', 'sell')
            ->call('save')
            ->assertHasErrors('price')
            ->set('kind', 'swap')
            ->set('price', '50000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->copy->listings()->sole()->price);
    }

    public function test_note_refuses_contact_details(): void
    {
        Livewire::actingAs($this->seller)
            ->test('pages::market.create', ['copy' => $this->copy])
            ->set('kind', 'swap')
            ->set('note', 'Call +998 90 123 45 67')
            ->call('save')
            ->assertHasErrors('note');
    }

    public function test_one_book_has_only_one_open_listing(): void
    {
        $first = $this->listing();
        $second = $this->copy->createListing($this->seller, ListingKind::Swap, null, null);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $this->copy->listings()->count());
    }

    public function test_viewer_and_stranger_cannot_offer_a_book(): void
    {
        $viewer = User::factory()->create();
        $this->library->members()->attach($viewer, ['role' => LibraryRole::Viewer->value]);

        Livewire::actingAs($viewer)->test('pages::market.create', ['copy' => $this->copy])->assertForbidden();
        Livewire::actingAs($this->visitor)->test('pages::market.create', ['copy' => $this->copy])->assertForbidden();
    }

    public function test_lost_book_cannot_be_offered_and_losing_it_closes_the_listing(): void
    {
        $listing = $this->listing();

        Livewire::actingAs($this->seller)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->set('status', CopyStatus::Lost->value)
            ->call('saveCopy');

        $this->assertSame(ListingStatus::Closed, $listing->refresh()->status);

        Livewire::actingAs($this->seller)->test('pages::market.create', ['copy' => $this->copy->fresh()])->assertForbidden();
    }

    public function test_seller_without_a_profile_name_is_asked_to_choose_one(): void
    {
        $this->seller->collectorProfile()->delete();

        Livewire::actingAs($this->seller->fresh())
            ->test('pages::market.create', ['copy' => $this->copy])
            ->assertSee(__('First choose the name other members will see.'))
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, $this->copy->listings()->count());
    }

    public function test_market_shows_the_book_but_no_private_details_of_the_seller(): void
    {
        $this->listing();

        $this->actingAs($this->visitor)
            ->get(route('market.index'))
            ->assertOk()
            ->assertSee('Kecha va kunduz')
            ->assertSee('Kitobxon')
            ->assertDontSee('Karim Olimov')
            ->assertDontSee('karim@example.com')
            ->assertDontSee('Olimovlar uyi')
            ->assertDontSee('Private note');

        $this->actingAs($this->visitor)
            ->get(route('market.show', $this->listing()))
            ->assertOk()
            ->assertSee('Kitobxon')
            ->assertDontSee('Karim Olimov')
            ->assertDontSee('karim@example.com')
            ->assertDontSee('Olimovlar uyi')
            ->assertDontSee('Private note');
    }

    public function test_market_search_and_type_filter_work(): void
    {
        $this->listing();
        $other = Copy::factory()->for($this->library)->for(Edition::factory()->create(['title' => 'Boshqa kitob']))->create();
        $other->createListing($this->seller, ListingKind::Swap, null, null);

        Livewire::actingAs($this->visitor)
            ->test('pages::market.index')
            ->set('search', 'kunduz')
            ->assertSee('Kecha va kunduz')
            ->assertDontSee('Boshqa kitob')
            ->set('search', '')
            ->set('kind', 'swap')
            ->assertSee('Boshqa kitob')
            ->assertDontSee('Kecha va kunduz');
    }

    public function test_closed_listing_disappears_for_others_but_the_seller_can_open_it(): void
    {
        $listing = $this->listing();

        Livewire::actingAs($this->visitor)->test('pages::market.show', ['listing' => $listing])->call('closeListing')->assertForbidden();

        Livewire::actingAs($this->seller)->test('pages::market.show', ['listing' => $listing])->call('closeListing')->assertRedirect();

        Livewire::actingAs($this->visitor)->test('pages::market.show', ['listing' => $listing->fresh()])->assertForbidden();
        Livewire::actingAs($this->seller)->test('pages::market.show', ['listing' => $listing->fresh()])->assertSee(__('This listing is closed.'));
    }

    public function test_giving_the_book_away_closes_its_listing(): void
    {
        $listing = $this->listing();
        $receiver = User::factory()->create();
        $receiverLibrary = Library::factory()->ownedBy($receiver)->create();

        $this->copy->offerTransfer($this->seller)->accept($receiver, $receiverLibrary);

        $this->assertSame(ListingStatus::Closed, $listing->refresh()->status);
    }

    public function test_deleting_the_book_closes_the_listing_and_keeps_its_title(): void
    {
        $listing = $this->listing();

        $this->copy->delete();

        $listing->refresh();
        $this->assertNull($listing->copy_id);
        $this->assertSame(ListingStatus::Closed, $listing->status);
        $this->assertSame('Kecha va kunduz', $listing->title);
    }

    public function test_listing_stops_showing_when_the_seller_leaves_the_library(): void
    {
        $member = User::factory()->create();
        $this->library->members()->attach($member, ['role' => LibraryRole::Member->value]);
        $listing = $this->copy->createListing($member, ListingKind::Swap, null, null);
        $this->assertTrue($listing->isVisible());

        $this->library->members()->detach($member);

        $this->assertFalse($listing->isVisible());
    }

    public function test_library_owner_can_close_a_members_listing(): void
    {
        $member = User::factory()->create();
        $this->library->members()->attach($member, ['role' => LibraryRole::Member->value]);
        $listing = $this->copy->createListing($member, ListingKind::Swap, null, null);

        $this->assertTrue($this->seller->can('close', $listing));
        $this->assertFalse($this->visitor->can('close', $listing));
    }

    public function test_hidden_listing_is_gone_from_the_market(): void
    {
        $listing = $this->listing();
        $listing->hideByModerator();

        $this->assertSame(0, Listing::query()->visibleTo($this->visitor)->count());
    }
}
