<?php

namespace Tests\Feature;

use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultipleLibrariesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Library $home;

    private Library $grandparents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->home = Library::factory()->ownedBy($this->user)->create(['name' => 'Home']);
        $this->grandparents = Library::factory()->withMember($this->user)->create(['name' => 'Grandparents']);
    }

    public function test_the_first_joined_library_is_current_by_default(): void
    {
        $this->assertTrue($this->user->currentLibrary()->is($this->home));
    }

    public function test_switching_changes_the_library_the_pages_show(): void
    {
        Copy::factory()->for($this->home)->for(Edition::factory()->state(['title' => 'Home book']))->create();
        Copy::factory()->for($this->grandparents)->for(Edition::factory()->state(['title' => 'Grandpa book']))->create();

        $this->actingAs($this->user)->get(route('books.index'))->assertSee('Home book')->assertDontSee('Grandpa book');

        $this->actingAs($this->user)->post(route('libraries.switch', $this->grandparents))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertTrue($this->user->fresh()->currentLibrary()->is($this->grandparents));
        $this->actingAs($this->user->fresh())->get(route('books.index'))->assertSee('Grandpa book')->assertDontSee('Home book');
    }

    public function test_cannot_switch_to_a_library_of_someone_else(): void
    {
        $stranger = Library::factory()->create();

        $this->actingAs($this->user)->post(route('libraries.switch', $stranger))->assertForbidden();

        $this->assertNull($this->user->fresh()->current_library_id);
    }

    public function test_a_left_library_is_no_longer_current(): void
    {
        $this->user->switchToLibrary($this->grandparents);
        $this->grandparents->members()->detach($this->user);

        $this->assertTrue($this->user->fresh()->currentLibrary()->is($this->home));
    }

    public function test_header_lists_the_other_libraries(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('libraries.switch', $this->grandparents))
            ->assertSee(__('All my libraries'));
    }

    public function test_libraries_page_lists_them_and_creates_a_new_one(): void
    {
        $this->actingAs($this->user)->get(route('libraries.index'))
            ->assertOk()
            ->assertSeeInOrder(['Home', 'Grandparents']);

        Livewire::actingAs($this->user)
            ->test('pages::library.index')
            ->set('newLibraryName', '  Summer house ')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $summerHouse = Library::firstWhere('name', 'Summer house');
        $this->assertTrue($summerHouse->isOwnedBy($this->user));
        $this->assertTrue($this->user->fresh()->currentLibrary()->is($summerHouse));
    }

    public function test_owners_are_limited_to_a_few_libraries(): void
    {
        foreach (range(2, User::MAX_OWNED_LIBRARIES) as $number) {
            $this->user->createLibrary("Library {$number}");
        }

        Livewire::actingAs($this->user)
            ->test('pages::library.index')
            ->set('newLibraryName', 'One too many')
            ->call('create')
            ->assertHasErrors('newLibraryName');

        $this->assertNull(Library::firstWhere('name', 'One too many'));
    }

    public function test_a_new_library_needs_a_name(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::library.index')
            ->set('newLibraryName', '')
            ->call('create')
            ->assertHasErrors(['newLibraryName' => 'required']);
    }
}
