<?php

namespace Tests\Feature;

use App\Enums\BookCondition;
use App\Enums\CopyStatus;
use App\Enums\LibraryRole;
use App\Models\Copy;
use App\Models\CopyTransfer;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Tag;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CopyTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $giver;

    private User $receiver;

    private Library $giverLibrary;

    private Library $receiverLibrary;

    private Copy $copy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->giver = User::factory()->create(['name' => 'Karim']);
        $this->receiver = User::factory()->create();
        $this->giverLibrary = Library::factory()->ownedBy($this->giver)->create(['name' => 'Karimovlar']);
        $this->receiverLibrary = Library::factory()->ownedBy($this->receiver)->create(['name' => 'Olimovlar']);
        $this->copy = Copy::factory()
            ->for($this->giverLibrary)
            ->for(Edition::factory()->create(['title' => 'Kecha va kunduz']))
            ->create(['added_by' => $this->giver->id]);
    }

    private function offeredTransfer(): CopyTransfer
    {
        return $this->copy->offerTransfer($this->giver);
    }

    public function test_member_creates_a_link_and_a_second_click_keeps_the_same_link(): void
    {
        $page = Livewire::actingAs($this->giver)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->call('offerTransfer');

        $transfer = $this->copy->transfers()->sole();
        $page->assertSee(route('transfers.show', $transfer->token));

        $page->call('offerTransfer');

        $this->assertSame(1, $this->copy->transfers()->count());
        $this->assertSame($this->giverLibrary->id, $this->copy->refresh()->library_id);
    }

    public function test_viewer_cannot_give_a_book_away(): void
    {
        $viewer = User::factory()->create();
        $this->giverLibrary->members()->attach($viewer, ['role' => LibraryRole::Viewer->value]);

        Livewire::actingAs($viewer)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->call('offerTransfer')
            ->assertForbidden();

        $this->assertSame(0, $this->copy->transfers()->count());
    }

    public function test_stranger_cannot_open_the_book_to_give_it_away(): void
    {
        Livewire::actingAs($this->receiver)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->assertForbidden();
    }

    public function test_lost_book_cannot_be_given_away(): void
    {
        $this->copy->update(['status' => CopyStatus::Lost]);

        Livewire::actingAs($this->giver)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->assertSee(__('Lost books cannot be given away.'))
            ->call('offerTransfer')
            ->assertForbidden();
    }

    public function test_accepting_moves_the_book_without_the_old_librarys_private_data(): void
    {
        $this->copy->update(['notes' => 'Private note', 'condition' => BookCondition::Fair]);
        $this->copy->tags()->attach(Tag::factory()->for($this->giverLibrary)->create());
        $this->copy->setHiddenFromGiftPage(true);
        $transfer = $this->offeredTransfer();

        Livewire::actingAs($this->receiver)
            ->test('pages::transfers.show', ['token' => $transfer->token])
            ->call('accept')
            ->assertRedirect(route('books.show', $this->copy));

        $copy = $this->copy->fresh();
        $this->assertSame($this->receiverLibrary->id, $copy->library_id);
        $this->assertSame($this->receiver->id, $copy->added_by);
        $this->assertNull($copy->notes);
        $this->assertFalse($copy->hidden_from_gift_page);
        $this->assertSame(0, $copy->tags()->count());
        $this->assertSame(BookCondition::Fair, $copy->condition);
        $this->assertSame(CopyStatus::Available, $copy->status);
        $this->assertTrue($this->receiver->fresh()->currentLibrary()->is($this->receiverLibrary));
    }

    public function test_after_the_move_only_the_new_library_has_the_book_and_sees_where_it_came_from(): void
    {
        $this->offeredTransfer()->accept($this->receiver, $this->receiverLibrary);

        Livewire::actingAs($this->giver)
            ->test('pages::books.show', ['copy' => $this->copy->fresh()])
            ->assertForbidden();

        Livewire::actingAs($this->receiver)
            ->test('pages::books.show', ['copy' => $this->copy->fresh()])
            ->assertSee('Karimovlar');
    }

    public function test_history_keeps_the_old_name_after_that_library_is_renamed_and_deleted(): void
    {
        $this->offeredTransfer()->accept($this->receiver, $this->receiverLibrary);

        $this->giverLibrary->update(['name' => 'New name']);
        $this->giverLibrary->delete();

        $this->assertSame(['Karimovlar'], $this->copy->fresh()->transferHistory()->pluck('from_library_name')->all());
    }

    public function test_history_lists_every_library_when_the_book_moves_again(): void
    {
        $third = User::factory()->create();
        $thirdLibrary = Library::factory()->ownedBy($third)->create();

        $this->offeredTransfer()->accept($this->receiver, $this->receiverLibrary);
        $this->copy->fresh()->offerTransfer($this->receiver)->accept($third, $thirdLibrary);

        $this->assertSame(['Karimovlar', 'Olimovlar'], $this->copy->fresh()->transferHistory()->pluck('from_library_name')->all());
    }

    public function test_link_works_only_once(): void
    {
        $transfer = $this->offeredTransfer();
        $transfer->accept($this->receiver, $this->receiverLibrary);

        $late = User::factory()->create();
        $lateLibrary = Library::factory()->ownedBy($late)->create();

        $this->assertFalse($transfer->accept($late, $lateLibrary));
        $this->assertSame($this->receiverLibrary->id, $this->copy->fresh()->library_id);
    }

    public function test_a_stale_copy_of_the_link_cannot_be_accepted_after_someone_else_took_it(): void
    {
        $stale = CopyTransfer::query()->findOrFail($this->offeredTransfer()->id);
        CopyTransfer::query()->findOrFail($stale->id)->accept($this->receiver, $this->receiverLibrary);

        $late = User::factory()->create();

        $this->assertFalse($stale->accept($late, Library::factory()->ownedBy($late)->create()));
        $this->assertSame($this->receiverLibrary->id, $this->copy->fresh()->library_id);
    }

    public function test_cancelled_link_stops_working_and_shows_no_book_details(): void
    {
        $transfer = $this->offeredTransfer();

        Livewire::actingAs($this->giver)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->call('cancelTransfer')
            ->assertSee(__('Create a link'));

        Livewire::actingAs($this->receiver)
            ->test('pages::transfers.show', ['token' => $transfer->token])
            ->assertSee(__('This link has expired, was cancelled or was already used. Ask for a new one.'))
            ->assertDontSee('Kecha va kunduz')
            ->assertDontSee('Karim')
            ->call('accept')
            ->assertForbidden();

        $this->assertSame($this->giverLibrary->id, $this->copy->fresh()->library_id);
    }

    public function test_expired_link_cannot_be_accepted(): void
    {
        $transfer = CopyTransfer::factory()->for($this->copy)->expired()->create();

        Livewire::actingAs($this->receiver)
            ->test('pages::transfers.show', ['token' => $transfer->token])
            ->call('accept')
            ->assertForbidden();
    }

    public function test_guest_sees_the_book_but_not_its_notes_and_returns_here_after_signing_up(): void
    {
        $this->copy->update(['notes' => 'Private note']);
        $url = route('transfers.show', $this->offeredTransfer()->token);

        $this->get($url)
            ->assertOk()
            ->assertSee('Kecha va kunduz')
            ->assertSee('Karim')
            ->assertDontSee('Private note')
            ->assertSee(__('Log in or sign up to accept the book.'))
            ->assertSessionHas('url.intended', $url);
    }

    public function test_guest_cannot_accept(): void
    {
        Livewire::test('pages::transfers.show', ['token' => $this->offeredTransfer()->token])
            ->call('accept')
            ->assertForbidden();
    }

    public function test_unknown_link_is_not_found(): void
    {
        $this->get(route('transfers.show', 'nope'))->assertNotFound();
    }

    public function test_unverified_user_must_verify_first(): void
    {
        $unverified = User::factory()->unverified()->create();
        Library::factory()->ownedBy($unverified)->create();

        Livewire::actingAs($unverified)
            ->test('pages::transfers.show', ['token' => $this->offeredTransfer()->token])
            ->assertSee(__('Verify your email first, then open this link again.'))
            ->call('accept')
            ->assertForbidden();
    }

    public function test_book_cannot_be_sent_to_a_library_the_user_only_views(): void
    {
        $viewer = User::factory()->create();
        $viewedLibrary = Library::factory()->ownedBy()->withMember($viewer, LibraryRole::Viewer)->create();
        Library::factory()->ownedBy($viewer)->create();

        Livewire::actingAs($viewer)
            ->test('pages::transfers.show', ['token' => $this->offeredTransfer()->token])
            ->set('libraryId', (string) $viewedLibrary->id)
            ->call('accept')
            ->assertHasErrors('libraryId');

        $this->assertSame($this->giverLibrary->id, $this->copy->fresh()->library_id);
    }

    public function test_book_cannot_be_sent_to_someone_elses_library(): void
    {
        Livewire::actingAs($this->receiver)
            ->test('pages::transfers.show', ['token' => $this->offeredTransfer()->token])
            ->set('libraryId', (string) Library::factory()->ownedBy()->create()->id)
            ->call('accept')
            ->assertHasErrors('libraryId');

        $this->assertSame($this->giverLibrary->id, $this->copy->fresh()->library_id);
    }

    public function test_giver_with_a_second_library_can_move_the_book_there(): void
    {
        $second = Library::factory()->ownedBy($this->giver)->create();

        Livewire::actingAs($this->giver)
            ->test('pages::transfers.show', ['token' => $this->offeredTransfer()->token])
            ->set('libraryId', (string) $second->id)
            ->call('accept')
            ->assertRedirect();

        $this->assertSame($second->id, $this->copy->fresh()->library_id);
    }

    public function test_giver_with_only_this_library_is_told_they_need_another(): void
    {
        Livewire::actingAs($this->giver)
            ->test('pages::transfers.show', ['token' => $this->offeredTransfer()->token])
            ->assertSee(__('You need another library where you can add books.'))
            ->call('accept')
            ->assertForbidden();
    }

    public function test_accepting_removes_a_matching_wish_in_the_new_library(): void
    {
        WishlistItem::factory()->for($this->receiverLibrary)->create(['title' => 'Kecha va kunduz']);

        $this->offeredTransfer()->accept($this->receiver, $this->receiverLibrary);

        $this->assertSame(0, $this->receiverLibrary->wishlistItems()->count());
    }

    public function test_marking_a_book_lost_cancels_its_open_link(): void
    {
        $transfer = $this->offeredTransfer();

        Livewire::actingAs($this->giver)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->set('status', CopyStatus::Lost->value)
            ->call('saveCopy')
            ->assertHasNoErrors();

        $this->assertFalse($transfer->refresh()->isUsable());
        $this->assertFalse($transfer->accept($this->receiver, $this->receiverLibrary));
    }

    public function test_condition_and_status_are_saved_and_validated(): void
    {
        Livewire::actingAs($this->giver)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->set('condition', 'torn')
            ->call('saveCopy')
            ->assertHasErrors('condition')
            ->set('condition', BookCondition::Good->value)
            ->call('saveCopy')
            ->assertHasNoErrors();

        $this->assertSame(BookCondition::Good, $this->copy->fresh()->condition);
    }

    public function test_lost_book_is_not_on_the_gift_page(): void
    {
        $this->giverLibrary->shareWithNewLink();
        $this->copy->update(['status' => CopyStatus::Lost]);

        $this->assertSame(0, $this->giverLibrary->giftPageCopies()->count());
    }
}
