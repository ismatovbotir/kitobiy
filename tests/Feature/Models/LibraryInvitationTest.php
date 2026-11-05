<?php

namespace Tests\Feature\Models;

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\LibraryInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LibraryInvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function unusableStates(): array
    {
        return [
            'accepted' => ['accepted'],
            'revoked' => ['revoked'],
            'expired' => ['expired'],
        ];
    }

    public function test_email_and_link_invitations_are_usable_and_pending(): void
    {
        $emailInvite = LibraryInvitation::factory()->create();
        $linkInvite = LibraryInvitation::factory()->link()->create();

        $this->assertTrue($emailInvite->isUsable());
        $this->assertTrue($linkInvite->isUsable());
        $this->assertTrue($linkInvite->isLink());
        $this->assertFalse($emailInvite->isLink());
        $this->assertEqualsCanonicalizing([$emailInvite->id, $linkInvite->id], LibraryInvitation::pending()->get()->modelKeys());
    }

    #[DataProvider('unusableStates')]
    public function test_invitation_is_not_usable_or_pending(string $state): void
    {
        $invitation = LibraryInvitation::factory()->link()->{$state}()->create();

        $this->assertFalse($invitation->isUsable());
        $this->assertEmpty(LibraryInvitation::pending()->get());
    }

    public function test_invitation_expires_after_the_lifetime(): void
    {
        $this->freezeTime();
        $invitation = LibraryInvitation::create([
            'library_id' => Library::factory()->create()->id,
            'invited_by' => User::factory()->create()->id,
            'role' => LibraryRole::Member,
        ]);

        $this->travel(7)->days();
        $this->travel(1)->second();

        $this->assertFalse($invitation->isUsable());
        $this->assertEmpty(LibraryInvitation::pending()->get());
    }

    public function test_invitation_defaults_token_and_expiry_when_created(): void
    {
        $this->freezeTime();

        $invitation = LibraryInvitation::create([
            'library_id' => Library::factory()->create()->id,
            'invited_by' => User::factory()->create()->id,
            'role' => LibraryRole::Member,
        ]);

        $this->assertSame(64, strlen($invitation->token));
        $this->assertSame(now()->addDays(7)->toDateTimeString(), $invitation->expires_at->toDateTimeString());
    }

    public function test_accepting_link_adds_user_as_member_and_kills_the_link(): void
    {
        $library = Library::factory()->ownedBy()->create();
        $invitation = LibraryInvitation::factory()->for($library)->link()->create();
        $user = User::factory()->create();

        $accepted = $invitation->accept($user);

        $this->assertTrue($accepted);
        $this->assertTrue($library->hasMember($user));
        $this->assertSame(LibraryRole::Member, $user->libraries()->first()->membership->role);
        $this->assertTrue($invitation->acceptedBy->is($user));
        $this->assertNotNull($invitation->accepted_at);
        $this->assertFalse($invitation->isUsable());
    }

    public function test_link_cannot_be_accepted_twice(): void
    {
        $library = Library::factory()->create();
        $invitation = LibraryInvitation::factory()->for($library)->link()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $staleCopy = LibraryInvitation::findOrFail($invitation->id);
        $invitation->accept($first);

        $accepted = $staleCopy->accept($second);

        $this->assertFalse($accepted);
        $this->assertFalse($library->hasMember($second));
        $this->assertTrue($invitation->refresh()->acceptedBy->is($first));
    }

    #[DataProvider('unusableStates')]
    public function test_unusable_invitation_cannot_be_accepted(string $state): void
    {
        $library = Library::factory()->create();
        $invitation = LibraryInvitation::factory()->for($library)->link()->{$state}()->create();
        $user = User::factory()->create();

        $accepted = $invitation->accept($user);

        $this->assertFalse($accepted);
        $this->assertFalse($library->hasMember($user));
    }

    public function test_accepting_does_not_downgrade_an_existing_owner(): void
    {
        $owner = User::factory()->create();
        $library = Library::factory()->ownedBy($owner)->create();
        $invitation = LibraryInvitation::factory()->for($library)->link()->create();

        $invitation->accept($owner);

        $this->assertTrue($library->isOwnedBy($owner));
        $this->assertDatabaseCount('library_user', 1);
    }

    public function test_deleting_accepting_user_keeps_invitation_record(): void
    {
        $user = User::factory()->create();
        $invitation = LibraryInvitation::factory()->accepted($user)->create();

        $user->delete();

        $this->assertNull($invitation->refresh()->accepted_by);
        $this->assertNotNull($invitation->accepted_at);
    }
}
