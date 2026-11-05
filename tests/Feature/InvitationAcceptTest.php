<?php

namespace Tests\Feature;

use App\Models\Copy;
use App\Models\Library;
use App\Models\LibraryInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationAcceptTest extends TestCase
{
    use RefreshDatabase;

    private Library $library;

    private LibraryInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->library = Library::factory()->ownedBy()->create(['name' => 'Karimovlar kutubxonasi']);
        $this->invitation = LibraryInvitation::factory()->for($this->library)->link()->create();
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get(route('invitations.show', 'nope'))->assertNotFound();
    }

    public function test_guest_sees_the_library_and_returns_here_after_signing_up(): void
    {
        $url = route('invitations.show', $this->invitation->token);

        $this->get($url)
            ->assertOk()
            ->assertSee('Karimovlar kutubxonasi')
            ->assertSee(__('Log in or sign up to accept the invitation.'))
            ->assertSessionHas('url.intended', $url);

        Livewire::test('pages::auth.register')
            ->set('name', 'Opa')
            ->set('email', 'opa@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertRedirect($url);
    }

    public function test_user_without_library_accepts_and_joins(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::invitations.show', ['token' => $this->invitation->token])
            ->call('accept')
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($this->library->hasMember($user));
        $this->assertFalse($this->invitation->refresh()->isUsable());
    }

    public function test_used_invitation_cannot_be_accepted(): void
    {
        $this->invitation->accept(User::factory()->create());
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::invitations.show', ['token' => $this->invitation->token])
            ->assertSee(__('This invitation has expired, was revoked or was already used. Ask for a new one.'))
            ->call('accept')
            ->assertForbidden();

        $this->assertFalse($this->library->hasMember($user));
    }

    public function test_user_whose_library_has_books_joins_a_second_one_and_keeps_theirs(): void
    {
        $user = User::factory()->create();
        $ownLibrary = Library::factory()->ownedBy($user)->create();
        Copy::factory()->for($ownLibrary)->create();

        Livewire::actingAs($user)
            ->test('pages::invitations.show', ['token' => $this->invitation->token])
            ->call('accept')
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($this->library->hasMember($user));
        $this->assertTrue($ownLibrary->fresh()->hasMember($user));
        $this->assertTrue($user->fresh()->currentLibrary()->is($this->library));
    }

    public function test_a_deliberately_created_empty_library_is_kept_when_joining(): void
    {
        $user = User::factory()->create();
        $home = Library::factory()->ownedBy($user)->create();
        Copy::factory()->for($home)->create();
        $emptyOne = $user->createLibrary('Dacha');

        $this->invitation->accept($user);

        $this->assertModelExists($emptyOne);
        $this->assertModelExists($home);
    }

    public function test_unused_default_library_is_replaced_when_joining_a_family_library(): void
    {
        $user = User::factory()->create();
        $defaultLibrary = $user->libraryOrCreateDefault();

        Livewire::actingAs($user)
            ->test('pages::invitations.show', ['token' => $this->invitation->token])
            ->call('accept')
            ->assertRedirect(route('dashboard'));

        $this->assertModelMissing($defaultLibrary);
        $this->assertTrue($user->currentLibrary()->is($this->library));
    }

    public function test_existing_member_is_told_they_already_belong(): void
    {
        $owner = $this->library->owners()->first();

        Livewire::actingAs($owner)
            ->test('pages::invitations.show', ['token' => $this->invitation->token])
            ->assertSee(__('You are already a member of this library.'))
            ->assertDontSee(__('Accept invitation'));
    }
}
