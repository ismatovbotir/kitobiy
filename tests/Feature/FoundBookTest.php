<?php

namespace Tests\Feature;

use App\Enums\CopyStatus;
use App\Enums\LibraryRole;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\FoundReport;
use App\Models\Library;
use App\Models\User;
use App\Notifications\BookFoundReported;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class FoundBookTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Library $library;

    private Copy $copy;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create(['name' => 'Karim Olimov', 'email' => 'karim@example.com']);
        $this->library = Library::factory()->ownedBy($this->owner)->create(['name' => 'Olimovlar uyi']);
        $this->copy = Copy::factory()
            ->for($this->library)
            ->for(Edition::factory()->create(['title' => 'Kecha va kunduz']))
            ->create(['notes' => 'Private note']);
    }

    private function send(string $body = 'I found it', string $contact = ''): void
    {
        Livewire::test('pages::copies.found', ['publicId' => $this->copy->public_id])
            ->set('body', $body)
            ->set('contact', $contact)
            ->call('send');
    }

    public function test_every_copy_gets_its_own_id(): void
    {
        $other = Copy::factory()->for($this->library)->create();

        $this->assertSame(26, strlen($this->copy->public_id));
        $this->assertNotSame($this->copy->public_id, $other->public_id);
        $this->assertIsInt($this->copy->getKey());
    }

    public function test_guest_sees_the_book_but_nothing_private(): void
    {
        $this->get($this->copy->foundPageUrl())
            ->assertOk()
            ->assertSee('Kecha va kunduz')
            ->assertSee('noindex, nofollow', false)
            ->assertDontSee('Olimovlar uyi')
            ->assertDontSee('Private note')
            ->assertDontSee('Karim Olimov')
            ->assertDontSee('karim@example.com');
    }

    public function test_upper_case_id_still_opens_the_page(): void
    {
        $this->get(route('copies.found', strtoupper($this->copy->public_id)))->assertOk();
    }

    public function test_unknown_id_and_the_plain_number_are_not_found(): void
    {
        $this->get(route('copies.found', 'nope'))->assertNotFound();
        $this->get(route('copies.found', (string) $this->copy->id))->assertNotFound();
    }

    public function test_library_name_shows_only_when_the_owner_allows_it(): void
    {
        Livewire::actingAs($this->owner)->test('pages::library.members')->call('toggleNameForFinders');

        $this->assertTrue($this->library->refresh()->show_name_to_finders);
        $this->get($this->copy->foundPageUrl())->assertSee('Olimovlar uyi');

        Livewire::actingAs($this->owner)->test('pages::library.members')->call('toggleNameForFinders');

        $this->get($this->copy->foundPageUrl())->assertDontSee('Olimovlar uyi');
    }

    public function test_member_cannot_change_whether_the_name_shows(): void
    {
        $member = User::factory()->create();
        $this->library->members()->attach($member, ['role' => LibraryRole::Member->value]);

        Livewire::actingAs($member)->test('pages::library.members')->call('toggleNameForFinders')->assertForbidden();

        $this->assertFalse($this->library->refresh()->show_name_to_finders);
    }

    public function test_lost_book_says_the_owner_is_looking_for_it(): void
    {
        $this->copy->update(['status' => CopyStatus::Lost]);

        $this->get($this->copy->foundPageUrl())->assertSee(__('The owner is looking for this book.'));
    }

    public function test_finder_sends_a_message_and_the_owner_gets_an_email_without_it(): void
    {
        $this->send('On a bench in the park', '+998901234567');

        $report = $this->copy->foundReports()->sole();
        $this->assertSame('On a bench in the park', $report->body);
        $this->assertSame('+998901234567', $report->finder_contact);

        Notification::assertSentTo($this->owner, BookFoundReported::class, function (BookFoundReported $notification): bool {
            $text = implode(' ', $notification->toMail($this->owner)->introLines);

            return ! str_contains($text, 'bench') && ! str_contains($text, '+998901234567');
        });
    }

    public function test_contact_is_optional_and_a_message_is_required(): void
    {
        Livewire::test('pages::copies.found', ['publicId' => $this->copy->public_id])
            ->call('send')
            ->assertHasErrors('body')
            ->set('body', 'Found it')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $this->assertNull($this->copy->foundReports()->sole()->finder_contact);
    }

    public function test_bot_filling_the_hidden_field_is_told_it_worked_but_nothing_is_saved(): void
    {
        Livewire::test('pages::copies.found', ['publicId' => $this->copy->public_id])
            ->set('body', 'Buy cheap things')
            ->set('website', 'https://spam.example')
            ->call('send')
            ->assertSet('sent', true);

        $this->assertSame(0, FoundReport::query()->count());
        Notification::assertNothingSent();
    }

    public function test_only_the_first_unread_message_sends_an_email(): void
    {
        $this->send('One');
        $this->send('Two');

        Notification::assertSentToTimes($this->owner, BookFoundReported::class, 1);
    }

    public function test_one_visitor_is_limited(): void
    {
        for ($sent = 0; $sent < FoundReport::MAX_PER_VISITOR_PER_HOUR; $sent++) {
            $this->send();
        }

        Livewire::test('pages::copies.found', ['publicId' => $this->copy->public_id])
            ->set('body', 'One more')
            ->call('send')
            ->assertHasErrors('body');

        $this->assertSame(FoundReport::MAX_PER_VISITOR_PER_HOUR, FoundReport::query()->count());
    }

    public function test_owner_reads_messages_and_opening_marks_them_read(): void
    {
        $this->send('On a bench', '+998901234567');

        $this->actingAs($this->owner);
        $this->assertSame(1, FoundReport::query()->visibleTo($this->owner)->whereNull('read_at')->count());

        Livewire::actingAs($this->owner)
            ->test('pages::dashboard')
            ->assertSee(route('library.found'));

        Livewire::actingAs($this->owner)
            ->test('pages::library.found')
            ->assertSee('On a bench')
            ->assertSee('+998901234567');

        $this->assertSame(0, FoundReport::query()->visibleTo($this->owner)->whereNull('read_at')->count());
    }

    public function test_plain_members_viewers_and_strangers_do_not_see_finders_contacts(): void
    {
        $this->send('On a bench', '+998901234567');

        $member = User::factory()->create();
        $this->library->members()->attach($member, ['role' => LibraryRole::Member->value]);

        foreach ([$member, User::factory()->create()] as $user) {
            Livewire::actingAs($user)
                ->test('pages::library.found')
                ->assertDontSee('On a bench')
                ->assertDontSee('+998901234567');
        }

        $this->assertNotNull($this->copy->foundReports()->sole());
        $this->assertNull($this->copy->foundReports()->sole()->read_at);
    }

    public function test_library_admin_sees_the_messages(): void
    {
        $this->send('On a bench');
        $admin = User::factory()->create();
        $this->library->members()->attach($admin, ['role' => LibraryRole::Admin->value]);

        Livewire::actingAs($admin)->test('pages::library.found')->assertSee('On a bench');
    }

    public function test_owner_deletes_a_message_but_nobody_else_can(): void
    {
        $this->send();
        $report = $this->copy->foundReports()->sole();

        Livewire::actingAs(User::factory()->create())
            ->test('pages::library.found')
            ->call('deleteReport', $report->id)
            ->assertNotFound();
        $this->assertModelExists($report);

        Livewire::actingAs($this->owner)->test('pages::library.found')->call('deleteReport', $report->id);
        $this->assertModelMissing($report);
    }

    public function test_id_moves_with_the_book_but_old_messages_do_not_reach_the_new_owner(): void
    {
        $this->send('On a bench', '+998901234567');
        $publicId = $this->copy->public_id;

        $receiver = User::factory()->create();
        $receiverLibrary = Library::factory()->ownedBy($receiver)->create();
        $this->copy->offerTransfer($this->owner)->accept($receiver, $receiverLibrary);

        $this->assertSame($publicId, $this->copy->fresh()->public_id);
        $this->assertSame(0, FoundReport::query()->count());

        Livewire::actingAs($receiver)->test('pages::library.found')->assertDontSee('+998901234567');

        $this->send('New message');
        Notification::assertSentTo($receiver, BookFoundReported::class);
    }

    public function test_book_page_shows_the_public_link_to_members(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::books.show', ['copy' => $this->copy])
            ->assertSee($this->copy->foundPageUrl());
    }
}
