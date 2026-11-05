<?php

namespace Tests\Feature\Models;

use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_duplicates_returns_copies_sharing_an_edition_in_the_same_library(): void
    {
        $library = Library::factory()->create();
        $edition = Edition::factory()->create();
        $duplicates = Copy::factory()->count(2)->for($library)->for($edition)->create();
        Copy::factory()->for($library)->create();
        Copy::factory()->for($edition)->create();

        $found = Copy::query()->whereBelongsTo($library)->exactDuplicates()->get();

        $this->assertEqualsCanonicalizing($duplicates->modelKeys(), $found->modelKeys());
    }

    public function test_exact_duplicates_ignores_same_edition_in_other_libraries(): void
    {
        $edition = Edition::factory()->create();
        Copy::factory()->count(2)->for($edition)->create();

        $found = Copy::query()->exactDuplicates()->get();

        $this->assertEmpty($found);
    }

    public function test_same_work_duplicates_returns_copies_of_different_editions_of_one_work(): void
    {
        $library = Library::factory()->create();
        $work = Work::factory()->create();
        $original = Copy::factory()->for($library)->for(Edition::factory()->ofWork($work))->create();
        $translation = Copy::factory()->for($library)->for(Edition::factory()->translation($work))->create();
        Copy::factory()->for(Edition::factory()->translation($work, 'en'))->create();

        $found = Copy::query()->whereBelongsTo($library)->sameWorkDuplicates()->get();

        $this->assertEqualsCanonicalizing([$original->id, $translation->id], $found->modelKeys());
    }

    public function test_same_work_duplicates_ignores_editions_without_work_and_same_edition_copies(): void
    {
        $library = Library::factory()->create();
        Copy::factory()->for($library)->for(Edition::factory())->create();
        Copy::factory()->for($library)->for(Edition::factory())->create();
        Copy::factory()->count(2)->for($library)->for(Edition::factory()->ofWork())->create();

        $found = Copy::query()->whereBelongsTo($library)->sameWorkDuplicates()->get();

        $this->assertEmpty($found);
    }

    public function test_deleting_user_keeps_copies_they_added(): void
    {
        $user = User::factory()->create();
        $copy = Copy::factory()->create(['added_by' => $user->id]);

        $user->delete();

        $this->assertNull($copy->refresh()->added_by);
    }

    public function test_copy_relationships_resolve(): void
    {
        $copy = Copy::factory()->create();

        $loaded = Copy::with(['library', 'edition'])->findOrFail($copy->id);

        $this->assertSame($copy->library_id, $loaded->library->id);
        $this->assertSame($copy->edition_id, $loaded->edition->id);
    }
}
