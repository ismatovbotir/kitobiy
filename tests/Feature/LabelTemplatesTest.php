<?php

namespace Tests\Feature;

use App\Models\LabelTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LabelTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_three_starting_templates_are_inserted_small_to_large(): void
    {
        $templates = LabelTemplate::query()->ordered()->get();

        $this->assertCount(3, $templates);
        $this->assertSame([38.1, 63.5, 99.1], $templates->pluck('width_mm')->all());
        $this->assertTrue($templates->every(fn (LabelTemplate $template): bool => $template->elements !== []));
        $this->assertSame('38.1 × 21.2 mm', $templates->first()->sizeLabel());
    }

    public function test_label_pages_are_for_super_admins_only(): void
    {
        $user = User::factory()->create();
        $template = LabelTemplate::query()->first();

        $this->actingAs($user)->get(route('admin.labels.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.labels.edit', $template))->assertForbidden();
        Livewire::actingAs($user)->test('pages::admin.labels.edit', ['labelTemplate' => $template])->assertForbidden();
    }

    public function test_super_admin_sees_the_templates_and_the_designer(): void
    {
        $template = LabelTemplate::query()->ordered()->first();

        $this->actingAs($this->admin)
            ->get(route('admin.labels.index'))
            ->assertOk()
            ->assertSee($template->name)
            ->assertSee(route('admin.labels.create'));

        $this->actingAs($this->admin)
            ->get(route('admin.labels.edit', $template))
            ->assertOk()
            ->assertSee(__('Drag onto the label or click to add.'));

        $this->actingAs($this->admin)
            ->get(route('admin.labels.create'))
            ->assertOk()
            ->assertSee(__('New template'));
    }

    public function test_super_admin_creates_a_template_and_elements_are_kept_inside_the_label(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.labels.edit')
            ->set('name', 'Spine 30×20')
            ->set('widthMm', 30)
            ->set('heightMm', 20)
            ->set('elements', [
                ['type' => 'title', 'x' => 25, 'y' => 1, 'w' => 40, 'h' => 6, 'font_size' => 8, 'bold' => true, 'align' => 'left', 'text' => 'ignored'],
                ['type' => 'text', 'x' => 1, 'y' => 18, 'w' => 10, 'h' => 5, 'font_size' => 6, 'bold' => false, 'align' => 'center', 'text' => ' Ex libris '],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $template = LabelTemplate::where('name', 'Spine 30×20')->sole();

        // MySQL JSON columns don't keep key order, so compare each element sorted by key.
        $sortedByKey = fn (array $elements): array => array_map(function (array $element): array {
            ksort($element);

            return $element;
        }, $elements);

        $this->assertSame(
            $sortedByKey([
                ['type' => 'title', 'x' => 0, 'y' => 1, 'w' => 30, 'h' => 6, 'font_size' => 8, 'bold' => true, 'align' => 'left', 'text' => null],
                ['type' => 'text', 'x' => 1, 'y' => 15, 'w' => 10, 'h' => 5, 'font_size' => 6, 'bold' => false, 'align' => 'center', 'text' => 'Ex libris'],
            ]),
            $sortedByKey($template->elements),
        );
    }

    public function test_unknown_elements_and_bad_sizes_are_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.labels.edit')
            ->set('name', 'Broken')
            ->set('widthMm', 500)
            ->set('elements', [
                ['type' => 'barcode', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10, 'font_size' => 8, 'bold' => false, 'align' => 'justify', 'text' => null],
            ])
            ->call('save')
            ->assertHasErrors(['widthMm', 'elements.0.type', 'elements.0.align']);

        $this->assertDatabaseMissing('label_templates', ['name' => 'Broken']);
    }

    public function test_super_admin_edits_an_existing_template(): void
    {
        $template = LabelTemplate::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.labels.edit', ['labelTemplate' => $template])
            ->assertSet('name', $template->name)
            ->set('name', 'Renamed')
            ->set('isActive', false)
            ->call('save')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertSame('Renamed', $template->name);
        $this->assertFalse($template->is_active);
        $this->assertCount(2, $template->elements);
    }

    public function test_super_admin_hides_duplicates_and_deletes_templates(): void
    {
        $template = LabelTemplate::factory()->create(['name' => 'Medium']);

        $page = Livewire::actingAs($this->admin)->test('pages::admin.labels.index');

        $page->call('toggleActive', $template->id);
        $this->assertFalse($template->refresh()->is_active);

        $page->call('duplicate', $template->id)->assertRedirect();
        $copy = LabelTemplate::where('name', __(':name (copy)', ['name' => 'Medium']))->sole();
        $this->assertSame($template->elements, $copy->elements);

        $page->call('delete', $template->id);
        $this->assertModelMissing($template);
    }
}
