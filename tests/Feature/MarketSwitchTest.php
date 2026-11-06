<?php

namespace Tests\Feature;

use App\Enums\ListingKind;
use App\Models\CollectorProfile;
use App\Models\Conversation;
use App\Models\Copy;
use App\Models\Library;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private Copy $copy;

    private Listing $listing;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create();
        CollectorProfile::factory()->for($this->seller)->create();
        $this->buyer = User::factory()->create();
        CollectorProfile::factory()->for($this->buyer)->create();

        $library = Library::factory()->ownedBy($this->seller)->create();
        $this->copy = Copy::factory()->for($library)->create();
        $this->listing = Copy::factory()->for($library)->create()
            ->createListing($this->seller, ListingKind::Swap, null, null);
        $this->conversation = $this->listing->conversations()->create([
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
        ]);
    }

    public function test_every_market_and_message_page_is_gone_while_off(): void
    {
        config(['market.enabled' => false]);

        $this->actingAs($this->seller);

        $this->get(route('market.index'))->assertNotFound();
        $this->get(route('market.show', $this->listing))->assertNotFound();
        $this->get(route('market.create', $this->copy))->assertNotFound();
        $this->get(route('messages.index'))->assertNotFound();
        $this->get(route('messages.show', $this->conversation))->assertNotFound();
    }

    public function test_every_market_and_message_page_opens_while_on(): void
    {
        $this->actingAs($this->seller);

        $this->get(route('market.index'))->assertOk();
        $this->get(route('market.show', $this->listing))->assertOk();
        $this->get(route('market.create', $this->copy))->assertOk();
        $this->get(route('messages.index'))->assertOk();
        $this->get(route('messages.show', $this->conversation))->assertOk();
    }

    public function test_navigation_hides_market_and_messages_while_off(): void
    {
        config(['market.enabled' => false]);

        $this->actingAs($this->seller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('market.index'))
            ->assertDontSee(route('messages.index'));
    }

    public function test_navigation_shows_market_and_messages_while_on(): void
    {
        $this->actingAs($this->seller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('market.index'))
            ->assertSee(route('messages.index'));
    }

    public function test_book_page_hides_the_offer_block_while_off(): void
    {
        config(['market.enabled' => false]);

        $this->actingAs($this->seller)
            ->get(route('books.show', $this->listing->copy))
            ->assertOk()
            ->assertDontSee(__('Sell or swap'))
            ->assertDontSee(route('market.show', $this->listing));
    }

    public function test_book_page_shows_the_offer_block_while_on(): void
    {
        $this->actingAs($this->seller)
            ->get(route('books.show', $this->copy))
            ->assertOk()
            ->assertSee(__('Sell or swap'))
            ->assertSee(route('market.create', $this->copy));
    }

    public function test_page_left_open_cannot_send_a_message_after_the_switch_is_turned_off(): void
    {
        $openPage = Livewire::actingAs($this->buyer)
            ->test('pages::market.show', ['listing' => $this->listing])
            ->set('body', 'Is it still there?');

        config(['market.enabled' => false]);

        $openPage->call('ask')->assertNotFound();

        $this->assertSame(0, $this->conversation->messages()->count());
    }

    public function test_report_form_is_refused_while_off(): void
    {
        config(['market.enabled' => false]);

        Livewire::actingAs($this->buyer)
            ->test('report-form', ['type' => 'listing', 'reportableId' => $this->listing->id])
            ->assertNotFound();
    }

    public function test_existing_listings_and_messages_are_kept_while_off(): void
    {
        $this->conversation->messages()->create(['sender_id' => $this->buyer->id, 'body' => 'Hello']);

        config(['market.enabled' => false]);
        $this->actingAs($this->seller)->get(route('market.show', $this->listing))->assertNotFound();

        config(['market.enabled' => true]);
        $this->get(route('market.show', $this->listing))->assertOk();
        $this->get(route('messages.show', $this->conversation))->assertOk()->assertSee('Hello');
    }
}
