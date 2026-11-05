<?php

namespace Tests\Feature;

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\LibraryInvitation;
use App\Models\User;
use App\Notifications\LibraryInvitationSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryMembersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->library = Library::factory()
            ->ownedBy($this->owner)
            ->withMember($this->member)
            ->create();
    }

    public function test_user_without_library_gets_their_default_library(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('library.members'))->assertOk();

        $this->assertTrue($user->currentLibrary()->isOwnedBy($user));
    }

    public function test_members_page_lists_members(): void
    {
        $this->actingAs($this->member)
            ->get(route('library.members'))
            ->assertOk()
            ->assertSee($this->owner->name)
            ->assertSee($this->member->name)
            ->assertDontSee(__('Create invitation'));
    }

    public function test_owner_creates_a_link_invitation_without_sending_mail(): void
    {
        Notification::fake();

        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('invite')
            ->assertHasNoErrors();

        $invitation = $this->library->invitations()->sole();

        $this->assertTrue($invitation->isLink());
        $this->assertTrue($invitation->inviter->is($this->owner));
        $this->assertSame(LibraryRole::Member, $invitation->role);
        Notification::assertNothingSent();
    }

    public function test_owner_invites_by_email_and_the_link_is_mailed(): void
    {
        Notification::fake();

        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->set('inviteEmail', ' Opa@Example.com ')
            ->call('invite')
            ->assertHasNoErrors();

        $invitation = $this->library->invitations()->sole();
        $this->assertSame('opa@example.com', $invitation->email);

        Notification::assertSentOnDemand(
            LibraryInvitationSent::class,
            function (LibraryInvitationSent $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation): bool {
                $mail = $notification->toMail($notifiable);

                return $notifiable->routes['mail'] === 'opa@example.com'
                    && $mail->actionUrl === route('invitations.show', $invitation->token);
            },
        );
    }

    public function test_invitation_is_kept_when_the_email_cannot_be_sent(): void
    {
        $this->useFailingMailer();

        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->set('inviteEmail', 'opa@example.com')
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSee(__('Invitation created, but the email could not be sent. Copy the link and send it yourself.'));

        $this->assertSame('opa@example.com', $this->library->invitations()->sole()->email);
    }

    public function test_invalid_email_is_rejected(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->set('inviteEmail', 'not-an-email')
            ->call('invite')
            ->assertHasErrors(['inviteEmail' => 'email']);
    }

    public function test_open_invitations_are_capped(): void
    {
        LibraryInvitation::factory()->count(10)->for($this->library)->link()->create();

        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('invite')
            ->assertHasErrors('inviteEmail');

        $this->assertSame(10, $this->library->invitations()->count());
    }

    public function test_member_cannot_invite_rename_or_remove(): void
    {
        $component = fn () => Livewire::actingAs($this->member)->test('pages::library.members');

        $component()->call('invite')->assertForbidden();
        $component()->set('libraryName', 'Hijacked')->call('rename')->assertForbidden();
        $component()->call('removeMember', $this->owner->id)->assertForbidden();

        $this->assertNotSame('Hijacked', $this->library->refresh()->name);
    }

    public function test_owner_renames_the_library_and_the_slug_stays(): void
    {
        $slug = $this->library->slug;

        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->set('libraryName', 'Yangi nom')
            ->call('rename')
            ->assertHasNoErrors();

        $this->library->refresh();
        $this->assertSame('Yangi nom', $this->library->name);
        $this->assertSame($slug, $this->library->slug);
    }

    public function test_owner_revokes_an_invitation(): void
    {
        $invitation = LibraryInvitation::factory()->for($this->library)->link()->create();

        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('revoke', $invitation->id);

        $this->assertFalse($invitation->refresh()->isUsable());
    }

    public function test_owner_cannot_revoke_another_librarys_invitation(): void
    {
        $foreign = LibraryInvitation::factory()->link()->create();

        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('revoke', $foreign->id);

        $this->assertTrue($foreign->refresh()->isUsable());
    }

    public function test_owner_removes_a_member(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('removeMember', $this->member->id);

        $this->assertFalse($this->library->hasMember($this->member));
    }

    public function test_owner_cannot_remove_themselves(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('removeMember', $this->owner->id)
            ->assertForbidden();

        $this->assertTrue($this->library->isOwnedBy($this->owner));
    }

    public function test_member_can_leave_but_owner_cannot(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('leave')
            ->assertForbidden();

        Livewire::actingAs($this->member)
            ->test('pages::library.members')
            ->call('leave')
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($this->library->hasMember($this->member));
        $this->assertTrue($this->library->isOwnedBy($this->owner));
    }
}
