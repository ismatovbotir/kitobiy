<?php

namespace Tests\Feature\Models;

use App\Enums\LibraryRole;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\LibraryInvitation;
use App\Models\Tag;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_library_is_private_by_default(): void
    {
        $library = Library::create(['name' => 'Home', 'slug' => 'home']);

        $this->assertFalse($library->refresh()->is_public);
    }

    public function test_slug_must_be_unique(): void
    {
        Library::factory()->create(['slug' => 'home']);

        $this->expectException(UniqueConstraintViolationException::class);

        Library::factory()->create(['slug' => 'home']);
    }

    public function test_owners_returns_only_members_with_owner_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $library = Library::factory()->ownedBy($owner)->withMember($member)->create();

        $owners = $library->owners()->get();

        $this->assertSame([$owner->id], $owners->modelKeys());
        $this->assertSame(LibraryRole::Owner, $owners->first()->membership->role);
        $this->assertTrue($library->isOwnedBy($owner));
        $this->assertFalse($library->isOwnedBy($member));
        $this->assertTrue($library->hasMember($member));
    }

    public function test_user_can_belong_to_many_libraries_with_different_roles(): void
    {
        $user = User::factory()->create();
        $home = Library::factory()->ownedBy($user)->create();
        $office = Library::factory()->withMember($user, LibraryRole::Member)->create();

        $libraries = $user->libraries()->orderBy('libraries.id')->get();

        $this->assertSame([$home->id, $office->id], $libraries->modelKeys());
        $this->assertSame(LibraryRole::Member, $libraries->last()->membership->role);
        $this->assertSame([$home->id], $user->ownedLibraries()->get()->modelKeys());
    }

    public function test_same_user_cannot_join_a_library_twice(): void
    {
        $user = User::factory()->create();
        $library = Library::factory()->ownedBy($user)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        $library->members()->attach($user, ['role' => LibraryRole::Member->value]);
    }

    public function test_deleting_library_removes_its_contents_but_keeps_shared_catalog(): void
    {
        $user = User::factory()->create();
        $library = Library::factory()->ownedBy($user)->create();
        $tag = Tag::factory()->for($library)->create();
        $invitation = LibraryInvitation::factory()->for($library)->create(['invited_by' => $user->id]);
        $edition = Edition::factory()->ofWork()->create();
        $copy = Copy::factory()->for($library)->for($edition)->create();
        $copy->tags()->attach($tag);

        $library->delete();

        $this->assertModelMissing($tag);
        $this->assertModelMissing($invitation);
        $this->assertModelMissing($copy);
        $this->assertDatabaseEmpty('library_user');
        $this->assertDatabaseEmpty('copy_tag');
        $this->assertModelExists($user);
        $this->assertModelExists($edition);
        $this->assertModelExists(Work::findOrFail($edition->work_id));
    }

    public function test_same_tag_name_is_allowed_in_different_libraries(): void
    {
        Tag::factory()->for(Library::factory())->create(['name' => 'Poetry']);
        Tag::factory()->for(Library::factory())->create(['name' => 'Poetry']);

        $this->assertSame(2, Tag::where('name', 'Poetry')->count());
    }

    public function test_tag_name_cannot_repeat_in_the_same_library(): void
    {
        $library = Library::factory()->create();
        Tag::factory()->for($library)->create(['name' => 'Poetry']);

        $this->expectException(UniqueConstraintViolationException::class);

        Tag::factory()->for($library)->create(['name' => 'Poetry']);
    }

    public function test_duplicate_editions_lists_editions_with_more_than_one_copy_in_this_library(): void
    {
        $library = Library::factory()->create();
        $duplicated = Edition::factory()->create(['title' => 'Duplicated']);
        $single = Edition::factory()->create();
        $elsewhere = Edition::factory()->create();
        Copy::factory()->count(3)->for($library)->for($duplicated)->create();
        Copy::factory()->for($library)->for($single)->create();
        Copy::factory()->for($library)->for($elsewhere)->create();
        Copy::factory()->for($elsewhere)->create();
        Copy::factory()->for($duplicated)->create();

        $duplicates = $library->duplicateEditions();

        $this->assertSame([$duplicated->id], $duplicates->modelKeys());
        $this->assertSame(3, $duplicates->first()->copies_count);
    }

    public function test_same_work_duplicates_lists_works_held_in_several_editions(): void
    {
        $library = Library::factory()->create();
        $work = Work::factory()->create();
        $original = Edition::factory()->ofWork($work)->create(['language' => 'ru']);
        $translation = Edition::factory()->translation($work)->create();
        $notInLibrary = Edition::factory()->translation($work, 'en')->create();
        $soloWork = Edition::factory()->ofWork()->create();
        Copy::factory()->for($library)->for($original)->create();
        Copy::factory()->for($library)->for($translation)->create();
        Copy::factory()->count(2)->for($library)->for($soloWork)->create();
        Copy::factory()->for($notInLibrary)->create();

        $works = $library->sameWorkDuplicates();

        $this->assertSame([$work->id], $works->modelKeys());
        $this->assertEqualsCanonicalizing([$original->id, $translation->id], $works->first()->editions->modelKeys());
    }
}
