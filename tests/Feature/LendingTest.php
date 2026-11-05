<?php

namespace Tests\Feature;

use App\Enums\CopyStatus;
use App\Enums\LibraryRole;
use App\Enums\ListingKind;
use App\Models\CollectorProfile;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LendingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $borrower;

    private Library $library;

    private Library $borrowerLibrary;

    private Copy $copy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Karim']);
        $this->borrower = User::factory()->create();
        $this->library = Library::factory()->ownedBy($this->owner)->create(['name' => 'Karimovlar']);
        $this->borrowerLibrary = Library::factory()->ownedBy($this->borrower)->create(['name' => 'Olimovlar']);
        $this->copy = Copy::factory()
            ->for($this->library)
            ->for(Edition::factory()->create(['title' => 'Kecha va kunduz']))
            ->create(['notes' => 'Private note']);
    }

    private function bookPage(?User $user = null): mixed
    {
        return Livewire::actingAs($user ?? $this->owner)->test('pages::books.show', ['copy' => $this->copy]);
    }

    public function test_owner_lends_to_a_person_and_the_book_stays_in_the_library(): void
    {
        $this->bookPage()
            ->set('borrowerName', 'Anvar aka')
            ->set('borrowerPhone', '+998901234567')
            ->set('dueOn', now(Loan::TIMEZONE)->addWeek()->toDateString())
            ->call('lendToPerson')
            ->assertHasNoErrors()
            ->assertSee('Anvar aka')
            ->assertSee('+998901234567');

        $loan = $this->copy->activeLoan();
        $this->assertSame('Anvar aka', $loan->borrower_name);
        $this->assertSame($this->library->id, $this->copy->fresh()->library_id);
    }

    public function test_name_is_required_and_the_date_cannot_be_in_the_past(): void
    {
        $this->bookPage()
            ->call('lendToPerson')
            ->assertHasErrors('borrowerName')
            ->set('borrowerName', 'Anvar')
            ->set('dueOn', now(Loan::TIMEZONE)->subDay()->toDateString())
            ->call('lendToPerson')
            ->assertHasErrors('dueOn');

        $this->assertNull($this->copy->activeLoan());
    }

    public function test_a_book_cannot_be_lent_twice(): void
    {
        $this->copy->lendToPerson($this->owner, 'Anvar', null, null);

        $this->bookPage()->set('borrowerName', 'Bobur')->call('lendToPerson')->assertForbidden();

        $this->assertSame('Anvar', $this->copy->activeLoan()->borrower_name);
        $this->assertSame(1, $this->copy->loans()->count());
    }

    public function test_viewer_and_stranger_cannot_lend(): void
    {
        $viewer = User::factory()->create();
        $this->library->members()->attach($viewer, ['role' => LibraryRole::Viewer->value]);

        $this->bookPage($viewer)->set('borrowerName', 'Anvar')->call('lendToPerson')->assertForbidden();
        $this->bookPage($this->borrower)->assertForbidden();
    }

    public function test_phone_is_shown_only_to_those_who_run_the_library(): void
    {
        $this->copy->lendToPerson($this->owner, 'Anvar', '+998901234567', null);
        $member = User::factory()->create();
        $this->library->members()->attach($member, ['role' => LibraryRole::Member->value]);
        $member->switchToLibrary($this->library);

        $this->bookPage($member)->assertSee('Anvar')->assertDontSee('+998901234567');

        Livewire::actingAs($member)->test('pages::library.loans')->assertSee('Anvar')->assertDontSee('+998901234567');
        Livewire::actingAs($this->owner)->test('pages::library.loans')->assertSee('+998901234567');
    }

    public function test_coming_back_erases_the_phone_and_the_book_can_be_lent_again(): void
    {
        $loan = $this->copy->lendToPerson($this->owner, 'Anvar', '+998901234567', null);

        $this->bookPage()->call('markLoanReturned')->assertDontSee('+998901234567')->assertSee('Anvar');

        $loan->refresh();
        $this->assertNotNull($loan->returned_at);
        $this->assertNull($loan->borrower_phone);
        $this->assertNull($this->copy->activeLoan());
        $this->assertTrue($this->owner->can('lend', $this->copy));
    }

    public function test_earlier_loan_record_can_be_deleted_only_after_the_book_is_back(): void
    {
        $loan = $this->copy->lendToPerson($this->owner, 'Anvar', null, null);

        $this->bookPage()->call('deleteLoanRecord', $loan->id)->assertNotFound();
        $this->assertModelExists($loan);

        $loan->markReturned();

        $this->bookPage()->call('deleteLoanRecord', $loan->id);
        $this->assertModelMissing($loan);
    }

    public function test_overdue_is_decided_in_uzbekistan_time(): void
    {
        $this->travelTo('2026-11-10 20:00:00');

        $dueToday = Loan::factory()->for($this->copy)->create(['due_on' => '2026-11-11']);
        $dueYesterday = Loan::factory()->for($this->copy)->create(['due_on' => '2026-11-10']);

        $this->assertFalse($dueToday->isOverdue());
        $this->assertTrue($dueYesterday->isOverdue());

        $dueYesterday->markReturned();
        $this->assertFalse($dueYesterday->isOverdue());
    }

    public function test_library_borrows_through_a_link_and_sees_it_without_private_details(): void
    {
        $page = $this->bookPage()->call('createLoanLink');
        $loan = $this->copy->pendingLoanLink();
        $page->assertSee(route('loans.show', $loan->token));

        Livewire::actingAs($this->borrower)
            ->test('pages::loans.show', ['token' => $loan->token])
            ->assertSee('Kecha va kunduz')
            ->assertDontSee('Private note')
            ->call('accept')
            ->assertRedirect(route('library.loans'));

        $active = $this->copy->activeLoan();
        $this->assertSame($this->borrowerLibrary->id, $active->borrower_library_id);
        $this->assertSame('Olimovlar', $active->borrower_name);
        $this->assertSame($this->library->id, $this->copy->fresh()->library_id);

        Livewire::actingAs($this->borrower)
            ->test('pages::books.index')
            ->assertSee('Kecha va kunduz')
            ->assertSee('Karimovlar')
            ->assertDontSee('Private note');

        Livewire::actingAs($this->borrower)
            ->test('pages::library.loans')
            ->assertSee('Kecha va kunduz')
            ->assertSee('Karimovlar');

        $this->assertSame(0, $this->borrowerLibrary->copies()->count());
        $this->bookPage($this->borrower)->assertForbidden();
    }

    public function test_link_works_once_and_a_stale_copy_of_it_fails(): void
    {
        $link = $this->copy->offerLoan($this->owner, null);
        $stale = Loan::query()->findOrFail($link->id);

        $this->assertTrue($link->acceptFor($this->borrower, $this->borrowerLibrary));

        $late = User::factory()->create();
        $this->assertFalse($stale->acceptFor($late, Library::factory()->ownedBy($late)->create()));
        $this->assertSame($this->borrowerLibrary->id, $this->copy->activeLoan()->borrower_library_id);
    }

    public function test_second_click_keeps_one_link(): void
    {
        $this->copy->offerLoan($this->owner, null);
        $this->copy->offerLoan($this->owner, null);

        $this->assertSame(1, $this->copy->loans()->count());
    }

    public function test_cancelled_or_expired_link_shows_nothing_and_cannot_be_accepted(): void
    {
        $cancelled = $this->copy->offerLoan($this->owner, null);
        $this->bookPage()->call('cancelLoanLink');

        Livewire::actingAs($this->borrower)
            ->test('pages::loans.show', ['token' => $cancelled->token])
            ->assertSee(__('This link has expired, was cancelled or was already used. Ask for a new one.'))
            ->assertDontSee('Kecha va kunduz')
            ->assertDontSee('Karim')
            ->call('accept')
            ->assertForbidden();

        $expired = Loan::factory()->for($this->copy)->expiredLink()->create();

        Livewire::actingAs($this->borrower)
            ->test('pages::loans.show', ['token' => $expired->token])
            ->call('accept')
            ->assertForbidden();

        $this->assertNull($this->copy->activeLoan());
    }

    public function test_guest_returns_to_the_link_and_unverified_user_must_verify(): void
    {
        $link = $this->copy->offerLoan($this->owner, null);
        $url = route('loans.show', $link->token);

        $this->get($url)->assertOk()->assertSessionHas('url.intended', $url);

        Livewire::test('pages::loans.show', ['token' => $link->token])->call('accept')->assertForbidden();

        $unverified = User::factory()->unverified()->create();
        Library::factory()->ownedBy($unverified)->create();

        Livewire::actingAs($unverified)
            ->test('pages::loans.show', ['token' => $link->token])
            ->assertSee(__('Verify your email first, then open this link again.'))
            ->call('accept')
            ->assertForbidden();
    }

    public function test_cannot_borrow_into_someone_elses_library_or_ones_own_book(): void
    {
        $link = $this->copy->offerLoan($this->owner, null);

        Livewire::actingAs($this->borrower)
            ->test('pages::loans.show', ['token' => $link->token])
            ->set('libraryId', (string) $this->library->id)
            ->call('accept')
            ->assertHasErrors('libraryId');

        Livewire::actingAs($this->owner)
            ->test('pages::loans.show', ['token' => $link->token])
            ->assertSee(__('You need another library where you can add books.'))
            ->call('accept')
            ->assertForbidden();

        $this->assertNull($this->copy->activeLoan());
    }

    public function test_only_the_owner_side_marks_a_library_loan_returned(): void
    {
        $link = $this->copy->offerLoan($this->owner, null);
        $link->acceptFor($this->borrower, $this->borrowerLibrary);

        $this->bookPage($this->borrower)->assertForbidden();

        $this->bookPage()->call('markLoanReturned');

        $this->assertNull($this->copy->activeLoan());
        Livewire::actingAs($this->borrower)->test('pages::books.index')->assertDontSee('Kecha va kunduz');
    }

    public function test_book_on_loan_cannot_be_given_away_or_offered_on_the_market(): void
    {
        CollectorProfile::factory()->for($this->owner)->create();
        $this->copy->lendToPerson($this->owner, 'Anvar', null, null);

        $this->bookPage()->call('offerTransfer')->assertForbidden();
        Livewire::actingAs($this->owner)->test('pages::market.create', ['copy' => $this->copy])->assertForbidden();
    }

    public function test_open_giving_link_cannot_move_a_book_that_went_out_on_loan(): void
    {
        $transfer = $this->copy->offerTransfer($this->owner);
        Loan::factory()->for($this->copy)->create();

        $this->assertFalse($transfer->accept($this->borrower, $this->borrowerLibrary));
        $this->assertSame($this->library->id, $this->copy->fresh()->library_id);
    }

    public function test_giving_link_and_lending_link_block_each_other(): void
    {
        $this->copy->offerLoan($this->owner, null);
        $this->assertFalse($this->owner->can('transfer', $this->copy));

        $this->copy->pendingLoanLink()->cancelLink();
        $this->copy->offerTransfer($this->owner);
        $this->assertFalse($this->owner->can('lend', $this->copy));
    }

    public function test_marking_lost_cancels_a_lending_link(): void
    {
        $link = $this->copy->offerLoan($this->owner, null);

        $this->bookPage()->set('status', CopyStatus::Lost->value)->call('saveCopy')->assertHasNoErrors();

        $this->assertFalse($link->refresh()->isUsableLink());
        $this->assertFalse($link->acceptFor($this->borrower, $this->borrowerLibrary));
    }

    public function test_giving_a_book_away_removes_its_loan_records(): void
    {
        $this->copy->lendToPerson($this->owner, 'Anvar', null, null)->markReturned();

        $this->copy->offerTransfer($this->owner)->accept($this->borrower, $this->borrowerLibrary);

        $this->assertSame(0, $this->copy->loans()->count());
    }

    public function test_listing_leaves_the_market_while_the_book_is_out_and_returns_with_it(): void
    {
        CollectorProfile::factory()->for($this->owner)->create();
        $listing = $this->copy->createListing($this->owner, ListingKind::Swap, null, null);

        $loan = $this->copy->lendToPerson($this->owner, 'Anvar', null, null);
        $this->assertFalse($listing->fresh()->isVisible());

        $loan->markReturned();
        $this->assertTrue($listing->fresh()->isVisible());
    }
}
