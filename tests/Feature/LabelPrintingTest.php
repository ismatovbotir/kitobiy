<?php

namespace Tests\Feature;

use App\Enums\LabelElement;
use App\Enums\LibraryRole;
use App\Models\Author;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\LabelTemplate;
use App\Models\Library;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\LabelSheet;
use App\Support\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LabelPrintingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Library $library;

    private Copy $copy;

    private LabelTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->library = Library::factory()->ownedBy($this->owner)->create(['name' => 'Olimovlar']);
        $edition = Edition::factory()->create(['title' => 'Kecha va kunduz', 'isbn_13' => '9789943000000']);
        $edition->authors()->attach(Author::factory()->create(['name' => 'Cho‘lpon']), ['role' => 'author']);
        $this->copy = Copy::factory()->for($this->library)->for($edition)->create(['notes' => 'Private note']);
        $this->template = LabelTemplate::query()->active()->ordered()->firstOrFail();
    }

    /**
     * @return array<string, array{float, float, int, int}>
     */
    public static function standardSheets(): array
    {
        return [
            'Avery L7651' => [38.1, 21.2, 5, 13],
            'Avery L7160' => [63.5, 38.1, 3, 7],
            'Avery L7165' => [99.1, 67.7, 2, 4],
        ];
    }

    #[DataProvider('standardSheets')]
    public function test_standard_sheets_get_the_right_grid(float $width, float $height, int $columns, int $rows): void
    {
        $sheet = new LabelSheet($width, $height);

        $this->assertSame($columns, $sheet->columns());
        $this->assertSame($rows, $sheet->rows());
    }

    #[DataProvider('standardSheets')]
    public function test_every_label_stays_inside_the_page(float $width, float $height, int $columns, int $rows): void
    {
        $sheet = new LabelSheet($width, $height);
        $last = $sheet->position($sheet->labelsPerPage() - 1);
        $first = $sheet->position(0);

        $this->assertGreaterThan(0, $first['x']);
        $this->assertGreaterThan(0, $first['y']);
        $this->assertLessThanOrEqual(LabelSheet::PAGE_WIDTH_MM, $last['x'] + $width);
        $this->assertLessThanOrEqual(LabelSheet::PAGE_HEIGHT_MM, $last['y'] + $height);
    }

    public function test_grid_matches_the_real_avery_l7160_margins(): void
    {
        $sheet = new LabelSheet(63.5, 38.1);

        $this->assertEqualsWithDelta(7.25, $sheet->position(0)['x'], 0.01);
        $this->assertEqualsWithDelta(15.15, $sheet->position(0)['y'], 0.01);
        $this->assertEqualsWithDelta(7.25 + 66, $sheet->position(1)['x'], 0.01);
    }

    public function test_labels_flow_onto_a_new_page_and_skipping_leaves_blanks(): void
    {
        $sheet = new LabelSheet(99.1, 67.7);

        $pages = $sheet->pages(range(1, 9));
        $this->assertCount(2, $pages);
        $this->assertCount(8, $pages[0]);
        $this->assertCount(1, $pages[1]);

        $skipped = $sheet->pages(range(1, 3), 2);
        $this->assertCount(1, $skipped);
        $this->assertSame($sheet->position(2)['x'], $skipped[0][0]['x']);
        $this->assertSame($sheet->position(2)['y'], $skipped[0][0]['y']);
    }

    public function test_label_too_big_for_columns_still_gets_one(): void
    {
        $sheet = new LabelSheet(210, 210);

        $this->assertSame(1, $sheet->labelsPerPage());
    }

    public function test_qr_code_is_a_clean_inline_svg_and_differs_by_content(): void
    {
        $first = QrCode::svg('https://kitobiy.test/c/aaa');
        $second = QrCode::svg('https://kitobiy.test/c/bbb');

        $this->assertStringStartsWith('<svg', $first);
        $this->assertStringNotContainsString('<?xml', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_short_id_is_the_upper_case_end_of_the_public_id(): void
    {
        $short = $this->copy->shortBookId();

        $this->assertSame(6, strlen($short));
        $this->assertStringEndsWith(strtolower($short), $this->copy->public_id);
        $this->assertSame($short, strtoupper($short));
    }

    public function test_short_id_finds_the_book_only_inside_the_library_being_searched(): void
    {
        $other = Copy::factory()->for($this->library)->create();
        $short = $this->copy->shortBookId();

        $this->assertSame([$this->copy->id], $this->library->copies()->matching($short)->pluck('id')->all());
        $this->assertSame([$this->copy->id], $this->library->copies()->matching(strtolower($short))->pluck('id')->all());
        $this->assertNotContains($other->id, $this->library->copies()->matching($short)->pluck('id')->all());

        $stranger = Library::factory()->ownedBy()->create();
        $this->assertSame(0, $stranger->copies()->matching($short)->count());
    }

    public function test_search_with_a_short_id_does_not_escape_the_other_filters(): void
    {
        $other = Library::factory()->ownedBy()->create();
        Copy::factory()->for($other)->for($this->copy->edition)->create();

        $this->assertSame(1, $this->library->copies()->matching('Kecha')->count());
        $this->assertSame(1, $this->library->copies()->matching($this->copy->shortBookId())->count());
    }

    public function test_label_values_come_from_the_book(): void
    {
        $this->assertSame('Kecha va kunduz', $this->copy->labelValue(LabelElement::Title));
        $this->assertSame('Cho‘lpon', $this->copy->labelValue(LabelElement::Authors));
        $this->assertSame('Olimovlar', $this->copy->labelValue(LabelElement::Library));
        $this->assertSame('9789943000000', $this->copy->labelValue(LabelElement::Isbn));
        $this->assertSame($this->copy->shortBookId(), $this->copy->labelValue(LabelElement::InventoryCode));
    }

    public function test_sheet_shows_real_data_and_a_real_qr_code_but_no_private_notes(): void
    {
        $template = LabelTemplate::factory()->create([
            'width_mm' => 63.5,
            'height_mm' => 38.1,
            'elements' => [
                ['type' => 'title', 'x' => 1, 'y' => 1, 'w' => 40, 'h' => 6, 'font_size' => 8, 'bold' => true, 'align' => 'left', 'text' => null],
                ['type' => 'inventory_code', 'x' => 1, 'y' => 8, 'w' => 40, 'h' => 6, 'font_size' => 8, 'bold' => false, 'align' => 'left', 'text' => null],
                ['type' => 'qr_code', 'x' => 40, 'y' => 8, 'w' => 20, 'h' => 20, 'font_size' => 8, 'bold' => false, 'align' => 'left', 'text' => null],
            ],
        ]);

        Livewire::actingAs($this->owner)
            ->test('pages::library.labels')
            ->set('templateId', (string) $template->id)
            ->set('selectedIds', [(string) $this->copy->id])
            ->call('prepare')
            ->assertHasNoErrors()
            ->assertSee('Kecha va kunduz')
            ->assertSee($this->copy->shortBookId())
            ->assertSeeHtml('data-qr="'.$this->copy->foundPageUrl().'"')
            ->assertDontSee('Private note');
    }

    public function test_nothing_prints_until_books_are_chosen(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.labels')
            ->call('prepare')
            ->assertHasErrors('selectedIds')
            ->assertSet('showSheets', false);
    }

    public function test_another_librarys_book_cannot_be_put_on_a_sheet(): void
    {
        $foreign = Copy::factory()->for(Library::factory()->ownedBy()->create())->create();

        Livewire::actingAs($this->owner)
            ->test('pages::library.labels')
            ->set('selectedIds', [(string) $foreign->id])
            ->call('prepare')
            ->assertHasErrors('selectedIds.*')
            ->assertSet('showSheets', false);
    }

    public function test_preselected_book_from_the_book_page_is_ticked_but_a_foreign_one_is_not(): void
    {
        $foreign = Copy::factory()->for(Library::factory()->ownedBy()->create())->create();

        Livewire::actingAs($this->owner)
            ->withQueryParams(['book' => $this->copy->id])
            ->test('pages::library.labels')
            ->assertSet('selectedIds', [(string) $this->copy->id]);

        Livewire::actingAs($this->owner)
            ->withQueryParams(['book' => $foreign->id])
            ->test('pages::library.labels')
            ->assertSet('selectedIds', []);
    }

    public function test_hidden_template_cannot_be_used(): void
    {
        $hidden = LabelTemplate::factory()->create(['is_active' => false]);

        Livewire::actingAs($this->owner)
            ->test('pages::library.labels')
            ->set('templateId', (string) $hidden->id)
            ->set('selectedIds', [(string) $this->copy->id])
            ->call('prepare')
            ->assertHasErrors('templateId');
    }

    public function test_too_many_labels_and_a_bad_skip_are_refused(): void
    {
        Livewire::actingAs($this->owner)
            ->test('pages::library.labels')
            ->set('selectedIds', array_map('strval', range(1, 201)))
            ->call('prepare')
            ->assertHasErrors('selectedIds');

        Livewire::actingAs($this->owner)
            ->test('pages::library.labels')
            ->set('selectedIds', [(string) $this->copy->id])
            ->set('skip', 9999)
            ->call('prepare')
            ->assertHasErrors('skip');
    }

    public function test_select_all_takes_only_this_library_and_respects_the_search(): void
    {
        Copy::factory()->for(Library::factory()->ownedBy()->create())->create();
        $other = Copy::factory()->for($this->library)->for(Edition::factory()->create(['title' => 'Boshqa']))->create();

        $page = Livewire::actingAs($this->owner)->test('pages::library.labels')->call('selectAll');
        $this->assertEqualsCanonicalizing([(string) $this->copy->id, (string) $other->id], $page->get('selectedIds'));

        $page->set('search', 'Boshqa')->call('selectAll');
        $this->assertSame([(string) $other->id], $page->get('selectedIds'));
    }

    public function test_viewer_and_guest_cannot_print(): void
    {
        $viewer = User::factory()->create();
        $this->library->members()->attach($viewer, ['role' => LibraryRole::Viewer->value]);
        $viewer->switchToLibrary($this->library);

        Livewire::actingAs($viewer)->test('pages::library.labels')->assertForbidden();

        auth()->logout();
        $this->get(route('library.labels'))->assertRedirect(route('login'));
    }

    public function test_in_production_qr_links_use_the_configured_address_not_the_visitors_host(): void
    {
        config(['app.url' => 'https://kitobiy.example']);
        $this->app->detectEnvironment(fn (): string => 'production');
        (new AppServiceProvider($this->app))->boot();

        $this->app['request']->headers->set('HOST', 'evil.example');
        $this->app['url']->setRequest($this->app['request']);

        $this->assertStringStartsWith('https://kitobiy.example/c/', $this->copy->foundPageUrl());
    }

    public function test_admin_preview_keeps_sample_content(): void
    {
        $html = view('components.label-preview', ['template' => $this->template])->render();

        $this->assertStringNotContainsString('data-qr', $html);
    }
}
