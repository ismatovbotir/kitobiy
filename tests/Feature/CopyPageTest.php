<?php

namespace Tests\Feature;

use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CopyPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Library $library;

    private Copy $copy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->library = Library::factory()->withMember($this->user)->create();
        $this->copy = Copy::factory()->for($this->library)->create();
    }

    private function page(?Copy $copy = null): Testable
    {
        return Livewire::actingAs($this->user)->test('pages::books.show', ['copy' => $copy ?? $this->copy]);
    }

    public function test_member_sees_the_book(): void
    {
        $this->actingAs($this->user)->get(route('books.show', $this->copy))
            ->assertOk()
            ->assertSee($this->copy->edition->title);
    }

    public function test_copy_of_another_library_is_forbidden(): void
    {
        $foreignCopy = Copy::factory()->create();

        $this->actingAs($this->user)->get(route('books.show', $foreignCopy))->assertForbidden();
    }

    public function test_tags_and_notes_can_be_changed(): void
    {
        $tag = Tag::factory()->for($this->library)->create();

        $this->page()
            ->set('tagIds', [(string) $tag->id])
            ->set('newTag', 'Poetry')
            ->set('notes', 'Signed')
            ->call('saveCopy')
            ->assertHasNoErrors();

        $this->copy->refresh();
        $this->assertSame('Signed', $this->copy->notes);
        $this->assertEqualsCanonicalizing([$tag->name, 'Poetry'], $this->copy->tags->pluck('name')->all());
    }

    public function test_tag_of_another_library_is_rejected(): void
    {
        $this->page()
            ->set('tagIds', [(string) Tag::factory()->create()->id])
            ->call('saveCopy')
            ->assertHasErrors(['tagIds.0']);
    }

    public function test_edition_held_only_by_this_library_can_be_corrected(): void
    {
        $this->page()
            ->call('editEdition')
            ->set('title', 'Corrected title')
            ->set('authors', 'Abdulla Qodiriy, Cho‘lpon')
            ->set('language', 'uz')
            ->call('saveEdition')
            ->assertHasNoErrors()
            ->assertSet('editingEdition', false);

        $edition = $this->copy->edition->refresh();
        $this->assertSame('Corrected title', $edition->title);
        $this->assertSame('uz', $edition->language);
        $this->assertSame(['Abdulla Qodiriy', 'Cho‘lpon'], $edition->authors->pluck('name')->all());
    }

    public function test_edition_shared_with_another_library_cannot_be_corrected(): void
    {
        Copy::factory()->for($this->copy->edition)->create();

        $this->page()
            ->assertSee(__('Other libraries also have this edition, so its details cannot be changed here.'))
            ->call('editEdition')
            ->assertForbidden();
    }

    public function test_copy_can_be_linked_to_another_edition_of_the_same_work(): void
    {
        $translation = Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Tarjima']))->create();

        $this->page()
            ->set('linkSearch', 'Tarjima')
            ->assertSee('Tarjima')
            ->call('linkTo', $translation->id)
            ->assertSee('Tarjima');

        $this->assertNotNull($this->copy->edition->refresh()->work_id);
        $this->assertSame($this->copy->edition->work_id, $translation->edition->refresh()->work_id);
        $this->assertCount(1, $this->library->sameWorkDuplicates());
    }

    public function test_copy_of_another_library_cannot_be_linked(): void
    {
        $foreignCopy = Copy::factory()->create();

        $this->page()->call('linkTo', $foreignCopy->id)->assertNotFound();

        $this->assertNull($foreignCopy->edition->refresh()->work_id);
    }

    public function test_edition_can_be_unlinked(): void
    {
        $sibling = Copy::factory()->for($this->library)->create();
        $this->copy->edition->linkToSameWorkAs($sibling->edition);

        $this->page()->call('unlink');

        $this->assertNull($this->copy->edition->refresh()->work_id);
        $this->assertCount(0, $this->library->sameWorkDuplicates());
    }

    public function test_copy_can_be_removed_and_the_catalog_edition_is_kept(): void
    {
        $this->page()->call('delete')->assertRedirect(route('books.index'));

        $this->assertModelMissing($this->copy);
        $this->assertModelExists($this->copy->edition);
    }

    public function test_other_copies_of_the_same_edition_are_shown(): void
    {
        $otherCopy = Copy::factory()->for($this->library)->for($this->copy->edition)->create();

        $this->page()
            ->assertSee(__('Copy :number', ['number' => 1]))
            ->assertSee(route('books.show', $otherCopy));
    }
}
