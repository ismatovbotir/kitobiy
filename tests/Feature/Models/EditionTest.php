<?php

namespace Tests\Feature\Models;

use App\Enums\ContributorRole;
use App\Enums\EditionSource;
use App\Models\Author;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function isbnInputs(): array
    {
        return [
            'hyphenated isbn-13' => ['978-5-17-090630-7', '9785170906307'],
            'spaces and tabs' => [" 978 5 17\t090630 7 ", '9785170906307'],
            'lowercase x check digit' => ['0-306-40615-x', '030640615X'],
            'already normalized' => ['9785170906307', '9785170906307'],
            'empty string' => ['', null],
            'only separators' => [' - ', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('isbnInputs')]
    public function test_normalize_isbn_strips_separators_and_uppercases_x(?string $input, ?string $expected): void
    {
        $normalized = Edition::normalizeIsbn($input);

        $this->assertSame($expected, $normalized);
    }

    public function test_isbn_columns_are_normalized_when_saved(): void
    {
        $edition = Edition::factory()->create(['isbn_13' => '978-5-17-090630-7', 'isbn_10' => '0-306-40615-x']);

        $this->assertDatabaseHas('editions', ['id' => $edition->id, 'isbn_13' => '9785170906307', 'isbn_10' => '030640615X']);
    }

    public function test_where_isbn_finds_edition_by_any_formatting_of_either_isbn(): void
    {
        $edition = Edition::factory()->create(['isbn_13' => '9785170906307', 'isbn_10' => '030640615X']);
        Edition::factory()->create();

        $this->assertSame([$edition->id], Edition::whereIsbn('978-5-17-090630-7')->get()->modelKeys());
        $this->assertSame([$edition->id], Edition::whereIsbn('0 306 40615 x')->get()->modelKeys());
    }

    public function test_isbn_13_must_be_unique_regardless_of_formatting(): void
    {
        Edition::factory()->create(['isbn_13' => '9785170906307']);

        $this->expectException(UniqueConstraintViolationException::class);

        Edition::factory()->create(['isbn_13' => '978-5-17-090630-7']);
    }

    public function test_many_editions_can_have_no_isbn(): void
    {
        Edition::factory()->count(2)->withoutIsbn()->create(['isbn_10' => '']);

        $this->assertDatabaseCount('editions', 2);
        $this->assertDatabaseMissing('editions', ['isbn_13' => '']);
    }

    public function test_open_library_source_id_is_unique_per_source(): void
    {
        Edition::factory()->fromOpenLibrary()->create(['source_id' => 'OL123M']);
        Edition::factory()->count(2)->create(['source' => EditionSource::Manual, 'source_id' => null]);

        $this->expectException(UniqueConstraintViolationException::class);

        Edition::factory()->fromOpenLibrary()->create(['source_id' => 'OL123M']);
    }

    public function test_new_edition_defaults_to_manual_source(): void
    {
        $edition = Edition::create(['title' => "O'tkan kunlar"]);

        $this->assertSame(EditionSource::Manual, $edition->refresh()->source);
    }

    public function test_edition_with_copies_cannot_be_deleted(): void
    {
        $copy = Copy::factory()->create();

        $this->expectException(QueryException::class);

        $copy->edition->delete();
    }

    public function test_deleting_work_detaches_its_editions(): void
    {
        $edition = Edition::factory()->ofWork()->create();

        $edition->work->delete();

        $this->assertNull($edition->refresh()->work_id);
    }

    public function test_deleting_creator_keeps_edition(): void
    {
        $user = User::factory()->create();
        $edition = Edition::factory()->create(['created_by' => $user->id]);

        $user->delete();

        $this->assertNull($edition->refresh()->created_by);
    }

    public function test_authors_are_returned_in_position_order(): void
    {
        $edition = Edition::factory()->create();
        $second = Author::factory()->create();
        $first = Author::factory()->create();
        $edition->authors()->attach([$second->id => ['position' => 1], $first->id => ['position' => 0]]);

        $authors = $edition->authors()->get();

        $this->assertSame([$first->id, $second->id], $authors->modelKeys());
    }

    public function test_translation_state_links_edition_to_work_with_translator(): void
    {
        $work = Work::factory()->create();

        $edition = Edition::factory()->translation($work)->create();

        $this->assertTrue($edition->work->is($work));
        $this->assertSame('uz', $edition->language);
        $this->assertCount(1, $edition->contributorsWithRole(ContributorRole::Translator)->get());
        $this->assertCount(0, $edition->authors()->get());
    }

    public function test_one_person_can_have_several_roles_and_roles_sync_independently(): void
    {
        $edition = Edition::factory()->create();

        $edition->syncContributorNames(ContributorRole::Author, 'Abdulla Qodiriy');
        $edition->syncContributorNames(ContributorRole::Compiler, 'Abdulla Qodiriy, Ozod Sharafiddinov');
        $edition->syncContributorNames(ContributorRole::Translator, 'Muhammad Ali');

        $edition->syncContributorNames(ContributorRole::Translator, '');

        $this->assertSame(['Abdulla Qodiriy'], $edition->authors()->pluck('name')->all());
        $this->assertSame(['Abdulla Qodiriy', 'Ozod Sharafiddinov'], $edition->contributorsWithRole(ContributorRole::Compiler)->pluck('name')->all());
        $this->assertSame(0, $edition->contributorsWithRole(ContributorRole::Translator)->count());
        $this->assertSame(2, Author::count());
    }

    public function test_other_contributors_are_grouped_by_role_in_display_order(): void
    {
        $edition = Edition::factory()->create();
        $edition->syncContributorNames(ContributorRole::Illustrator, 'Rassom Bir');
        $edition->syncContributorNames(ContributorRole::Translator, 'Tarjimon Bir, Tarjimon Ikki');
        $edition->syncContributorNames(ContributorRole::Author, 'Muallif');

        $groups = $edition->load('contributors')->otherContributorsByRole();

        $this->assertSame(['translator', 'illustrator'], array_keys($groups));
        $this->assertSame(['Tarjimon Bir', 'Tarjimon Ikki'], $groups['translator']['people']->pluck('name')->all());
    }

    public function test_linking_two_editions_without_a_work_creates_one(): void
    {
        [$original, $translation] = Edition::factory()->count(2)->create();

        $translation->linkToSameWorkAs($original);

        $this->assertNotNull($original->refresh()->work_id);
        $this->assertSame($original->work_id, $translation->refresh()->work_id);
    }

    public function test_linking_editions_of_different_works_merges_the_works(): void
    {
        $workA = Work::factory()->create();
        $workB = Work::factory()->create();
        $a1 = Edition::factory()->ofWork($workA)->create();
        $a2 = Edition::factory()->ofWork($workA)->create();
        $b1 = Edition::factory()->ofWork($workB)->create();

        $a1->linkToSameWorkAs($b1);

        $this->assertSame($workB->id, $a1->refresh()->work_id);
        $this->assertSame($workB->id, $a2->refresh()->work_id);
        $this->assertModelMissing($workA);
    }

    public function test_unlinking_the_second_to_last_edition_removes_the_work(): void
    {
        $work = Work::factory()->create();
        $first = Edition::factory()->ofWork($work)->create();
        $second = Edition::factory()->ofWork($work)->create();

        $first->unlinkFromWork();

        $this->assertNull($first->refresh()->work_id);
        $this->assertNull($second->refresh()->work_id);
        $this->assertModelMissing($work);
    }

    public function test_sync_author_names_keeps_order_and_reuses_authors(): void
    {
        $edition = Edition::factory()->create();
        $existing = Author::factory()->create(['name' => 'Abdulla Qodiriy']);

        $edition->syncAuthorNames(' Cho‘lpon , Abdulla Qodiriy, , Cho‘lpon');

        $this->assertSame(['Cho‘lpon', 'Abdulla Qodiriy'], $edition->authors()->pluck('name')->all());
        $this->assertTrue($edition->authors->contains($existing));
    }
}
