<?php

namespace Tests\Feature;

use App\Enums\LibraryRole;
use App\Models\Copy;
use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $member;

    private User $viewer;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->member = User::factory()->create();
        $this->viewer = User::factory()->create();
        $this->library = Library::factory()
            ->ownedBy($this->owner)
            ->withMember($this->admin, LibraryRole::Admin)
            ->withMember($this->member)
            ->withMember($this->viewer, LibraryRole::Viewer)
            ->create();
    }

    private function roleOf(User $user): ?LibraryRole
    {
        return $this->library->roleOf($user);
    }

    public function test_owner_invites_with_any_role_below_owner(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->set('inviteRole', 'admin')
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertSame(LibraryRole::Admin, $this->library->invitations()->sole()->role);

        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->set('inviteRole', 'owner')
            ->call('invite')
            ->assertHasErrors('inviteRole');
    }

    public function test_an_invitation_gives_its_role_on_accept(): void
    {
        $invitation = $this->library->invitations()->create(['invited_by' => $this->owner->id, 'role' => LibraryRole::Viewer]);
        $grandma = User::factory()->create();

        $invitation->accept($grandma);

        $this->assertSame(LibraryRole::Viewer, $this->roleOf($grandma));
    }

    public function test_admin_invites_members_and_viewers_but_not_admins(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::library.members')
            ->set('inviteRole', 'viewer')
            ->call('invite')
            ->assertHasNoErrors()
            ->set('inviteRole', 'admin')
            ->call('invite')
            ->assertHasErrors('inviteRole');

        $this->assertSame([LibraryRole::Viewer], $this->library->invitations()->pluck('role')->all());
    }

    public function test_owner_changes_roles(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('changeRole', $this->member->id, 'admin')
            ->call('changeRole', $this->admin->id, 'viewer');

        $this->assertSame(LibraryRole::Admin, $this->roleOf($this->member));
        $this->assertSame(LibraryRole::Viewer, $this->roleOf($this->admin));
    }

    public function test_nobody_is_made_owner_or_changes_their_own_role(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.members')
            ->call('changeRole', $this->member->id, 'owner')
            ->assertForbidden();

        Livewire::actingAs($this->admin)
            ->test('pages::library.members')
            ->call('changeRole', $this->admin->id, 'member')
            ->assertForbidden();

        $this->assertSame(LibraryRole::Member, $this->roleOf($this->member));
        $this->assertSame(LibraryRole::Admin, $this->roleOf($this->admin));
    }

    public function test_admin_manages_members_and_viewers_but_not_owners_or_admins(): void
    {
        $otherAdmin = User::factory()->create();
        $this->library->members()->attach($otherAdmin, ['role' => LibraryRole::Admin->value]);

        $page = fn () => Livewire::actingAs($this->admin)->test('pages::library.members');

        $page()->call('changeRole', $this->member->id, 'viewer')->assertOk();
        $this->assertSame(LibraryRole::Viewer, $this->roleOf($this->member));

        $page()->call('changeRole', $this->member->id, 'admin')->assertForbidden();
        $page()->call('removeMember', $this->owner->id)->assertForbidden();
        $page()->call('removeMember', $otherAdmin->id)->assertForbidden();

        Livewire::actingAs($this->admin)->test('pages::library.members')->call('removeMember', $this->viewer->id)->assertOk();

        $this->assertFalse($this->library->hasMember($this->viewer));
        $this->assertTrue($this->library->hasMember($otherAdmin));
    }

    public function test_owner_removes_an_admin(): void
    {
        Livewire::actingAs($this->owner)->test('pages::library.members')->call('removeMember', $this->admin->id);

        $this->assertFalse($this->library->hasMember($this->admin));
    }

    public function test_admin_renames_the_library(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::library.members')
            ->set('libraryName', 'Renamed by admin')
            ->call('rename')
            ->assertHasNoErrors();

        $this->assertSame('Renamed by admin', $this->library->fresh()->name);
    }

    public function test_members_and_viewers_cannot_manage_people(): void
    {
        foreach ([$this->member, $this->viewer] as $user) {
            Livewire::actingAs($user)->test('pages::library.members')->call('invite')->assertForbidden();
            Livewire::actingAs($user)->test('pages::library.members')->call('changeRole', $this->member->id, 'viewer')->assertForbidden();
        }
    }

    public function test_viewer_sees_books_but_cannot_add_or_edit_them(): void
    {
        $copy = Copy::factory()->for($this->library)->create(['notes' => 'Gift from grandmother']);

        $this->actingAs($this->viewer)->get(route('books.index'))
            ->assertOk()
            ->assertSee($copy->edition->title)
            ->assertDontSee(route('books.create'));

        $this->actingAs($this->viewer)->get(route('books.show', $copy))
            ->assertOk()
            ->assertSee('Gift from grandmother')
            ->assertDontSee(__('Remove from library'));

        $this->actingAs($this->viewer)->get(route('books.create'))->assertForbidden();

        Livewire::actingAs($this->viewer)
            ->test('pages::books.show', ['copy' => $copy])
            ->set('notes', 'Changed')
            ->call('saveCopy')
            ->assertForbidden();

        Livewire::actingAs($this->viewer)
            ->test('pages::books.show', ['copy' => $copy])
            ->call('delete')
            ->assertForbidden();

        $this->assertSame('Gift from grandmother', $copy->fresh()->notes);
    }

    public function test_members_and_admins_edit_books(): void
    {
        $copy = Copy::factory()->for($this->library)->create();

        foreach ([$this->admin, $this->member] as $user) {
            $this->assertTrue($user->can('addBooks', $this->library));
            $this->assertTrue($user->can('update', $copy));
        }

        $this->assertFalse($this->viewer->can('addBooks', $this->library));
    }
}
