<?php

namespace Tests\Feature;

use App\Enums\ListingKind;
use App\Models\CollectorProfile;
use App\Models\Conversation;
use App\Models\Copy;
use App\Models\Library;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create(['name' => 'Karim Olimov']);
        CollectorProfile::factory()->for($this->seller)->create(['display_name' => 'Kitobxon']);
        $this->buyer = User::factory()->create(['name' => 'Anvar Sobirov']);
        CollectorProfile::factory()->for($this->buyer)->create(['display_name' => 'Xaridor']);

        $library = Library::factory()->ownedBy($this->seller)->create();
        $this->listing = Copy::factory()->for($library)->create()
            ->createListing($this->seller, ListingKind::Swap, null, null);
    }

    private function conversation(): Conversation
    {
        return $this->listing->conversations()->create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
    }

    public function test_buyer_starts_a_conversation_and_the_seller_gets_an_email_without_the_text(): void
    {
        Notification::fake();

        Livewire::actingAs($this->buyer)
            ->test('pages::market.show', ['listing' => $this->listing])
            ->set('body', 'Is it still there?')
            ->call('ask')
            ->assertRedirect();

        $conversation = $this->listing->conversations()->sole();
        $this->assertSame('Is it still there?', $conversation->messages()->sole()->body);

        Notification::assertSentTo($this->seller, NewMessageReceived::class, function (NewMessageReceived $notification): bool {
            $mail = $notification->toMail($this->seller);

            return ! str_contains(implode(' ', $mail->introLines), 'Is it still there?');
        });
    }

    public function test_only_the_first_unread_message_sends_an_email(): void
    {
        Notification::fake();
        $conversation = $this->conversation();

        $conversation->send($this->buyer, 'One');
        $conversation->send($this->buyer, 'Two');

        Notification::assertSentToTimes($this->seller, NewMessageReceived::class, 1);

        $conversation->markReadBy($this->seller);
        $conversation->send($this->buyer, 'Three');

        Notification::assertSentToTimes($this->seller, NewMessageReceived::class, 2);
    }

    public function test_a_second_question_uses_the_same_conversation(): void
    {
        Notification::fake();
        $this->conversation();

        Livewire::actingAs($this->buyer)
            ->test('pages::market.show', ['listing' => $this->listing])
            ->set('body', 'Hello again')
            ->call('ask');

        $this->assertSame(1, $this->listing->conversations()->count());
    }

    public function test_seller_cannot_message_their_own_listing(): void
    {
        Livewire::actingAs($this->seller)
            ->test('pages::market.show', ['listing' => $this->listing])
            ->set('body', 'Hello')
            ->call('ask')
            ->assertForbidden();
    }

    public function test_user_without_a_profile_name_cannot_start_a_conversation(): void
    {
        $this->buyer->collectorProfile()->delete();

        Livewire::actingAs($this->buyer->fresh())
            ->test('pages::market.show', ['listing' => $this->listing])
            ->assertSee(__('First choose the name other members will see.'))
            ->set('body', 'Hello')
            ->call('ask')
            ->assertForbidden();
    }

    public function test_only_the_two_people_can_read_a_conversation(): void
    {
        Notification::fake();
        $conversation = $this->conversation();
        $conversation->send($this->buyer, 'Secret question');

        Livewire::actingAs(User::factory()->create())
            ->test('pages::messages.show', ['conversation' => $conversation])
            ->assertForbidden();

        Livewire::actingAs($this->seller)
            ->test('pages::messages.show', ['conversation' => $conversation])
            ->assertSee('Secret question')
            ->assertSee('Xaridor')
            ->assertDontSee('Anvar Sobirov');
    }

    public function test_stranger_cannot_reply_and_a_list_shows_only_own_conversations(): void
    {
        Notification::fake();
        $conversation = $this->conversation();
        $conversation->send($this->buyer, 'Hi');
        $stranger = User::factory()->create();
        CollectorProfile::factory()->for($stranger)->create();

        $this->assertFalse($stranger->can('reply', $conversation));

        Livewire::actingAs($stranger)
            ->test('pages::messages.index')
            ->assertDontSee($this->listing->title);

        Livewire::actingAs($this->seller)
            ->test('pages::messages.index')
            ->assertSee($this->listing->title);
    }

    public function test_opening_a_conversation_marks_it_read_and_clears_the_count(): void
    {
        Notification::fake();
        $conversation = $this->conversation();
        $conversation->send($this->buyer, 'Hi');

        $this->assertSame(1, $this->seller->unreadMessagesCount());
        $this->assertSame(0, $this->buyer->unreadMessagesCount());

        Livewire::actingAs($this->seller)->test('pages::messages.show', ['conversation' => $conversation]);

        $this->assertSame(0, $this->seller->unreadMessagesCount());
    }

    public function test_seller_replies(): void
    {
        Notification::fake();
        $conversation = $this->conversation();

        Livewire::actingAs($this->seller)
            ->test('pages::messages.show', ['conversation' => $conversation])
            ->set('body', 'Yes, it is')
            ->call('reply')
            ->assertSet('body', '')
            ->assertSee('Yes, it is');

        Notification::assertSentTo($this->buyer, NewMessageReceived::class);
    }

    public function test_too_many_messages_are_refused(): void
    {
        Notification::fake();
        $conversation = $this->conversation();
        $page = Livewire::actingAs($this->buyer)->test('pages::messages.show', ['conversation' => $conversation]);

        for ($sent = 0; $sent < Message::MAX_PER_MINUTE; $sent++) {
            $page->set('body', 'Hello')->call('reply')->assertHasNoErrors();
        }

        $page->set('body', 'One more')->call('reply')->assertHasErrors('body');

        $this->assertSame(Message::MAX_PER_MINUTE, $conversation->messages()->count());
    }

    public function test_too_many_new_conversations_are_refused(): void
    {
        Notification::fake();
        $library = Library::factory()->ownedBy($this->seller)->create();

        for ($started = 0; $started < Message::MAX_NEW_CONVERSATIONS_PER_HOUR; $started++) {
            $listing = Copy::factory()->for($library)->create()->createListing($this->seller, ListingKind::Swap, null, null);

            Livewire::actingAs($this->buyer)
                ->test('pages::market.show', ['listing' => $listing])
                ->set('body', 'Hello')
                ->call('ask')
                ->assertHasNoErrors();
        }

        Livewire::actingAs($this->buyer)
            ->test('pages::market.show', ['listing' => $this->listing])
            ->set('body', 'Hello')
            ->call('ask')
            ->assertHasErrors('body');

        $this->assertSame(0, $this->listing->conversations()->count());
    }

    public function test_block_stops_messages_both_ways_and_hides_listings_but_keeps_the_history(): void
    {
        Notification::fake();
        $conversation = $this->conversation();
        $conversation->send($this->buyer, 'Old message');

        Livewire::actingAs($this->seller)
            ->test('pages::messages.show', ['conversation' => $conversation])
            ->call('blockOtherParty')
            ->assertRedirect(route('messages.index'));

        $this->assertFalse($this->buyer->can('reply', $conversation));
        $this->assertFalse($this->seller->can('reply', $conversation));
        $this->assertFalse($this->buyer->can('view', $this->listing));
        $this->assertSame(0, Listing::query()->visibleTo($this->buyer)->count());

        Livewire::actingAs($this->buyer)
            ->test('pages::messages.show', ['conversation' => $conversation])
            ->assertSee('Old message')
            ->assertSee(__('You cannot send messages in this conversation.'))
            ->set('body', 'Hello?')
            ->call('reply')
            ->assertForbidden();
    }

    public function test_blocked_person_can_be_unblocked_from_the_profile_page(): void
    {
        $this->buyer->block($this->seller);

        Livewire::actingAs($this->buyer)
            ->test('pages::profile.edit')
            ->assertSee('Kitobxon')
            ->assertDontSee('Karim Olimov')
            ->call('unblock', $this->seller->id);

        $this->assertFalse($this->buyer->hasBlockWith($this->seller));
    }

    public function test_message_time_is_shown_in_uzbekistan_time(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-05 19:09:00');

        $message = $this->conversation()->send($this->buyer, 'Hi');

        $this->assertSame('2026-11-06 00:09', $message->sentAtLabel());
    }

    public function test_user_cannot_block_themselves(): void
    {
        $this->seller->block($this->seller);

        $this->assertSame(0, $this->seller->blockedUsers()->count());
    }
}
