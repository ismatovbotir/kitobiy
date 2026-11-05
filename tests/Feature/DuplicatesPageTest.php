<?php

namespace Tests\Feature;

use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicatesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->library = Library::factory()->withMember($this->user)->create();
    }

    public function test_exact_and_same_work_duplicates_are_listed(): void
    {
        $twice = Edition::factory()->create(['title' => 'Owned twice']);
        Copy::factory()->count(2)->for($this->library)->for($twice)->create();
        Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Owned once']))->create();

        $work = Work::factory()->create();
        Copy::factory()->for($this->library)->for(Edition::factory()->ofWork($work)->state(['title' => 'Master i Margarita']))->create();
        Copy::factory()->for($this->library)->for(Edition::factory()->ofWork($work)->state(['title' => 'Usta va Margarita']))->create();

        $this->actingAs($this->user)->get(route('books.duplicates'))
            ->assertOk()
            ->assertSee('Owned twice')
            ->assertDontSee('Owned once')
            ->assertSee('Master i Margarita')
            ->assertSee('Usta va Margarita');
    }

    public function test_copies_in_other_libraries_do_not_count(): void
    {
        $edition = Edition::factory()->create(['title' => 'Shared edition']);
        Copy::factory()->for($this->library)->for($edition)->create();
        Copy::factory()->for($edition)->create();

        $this->actingAs($this->user)->get(route('books.duplicates'))
            ->assertOk()
            ->assertDontSee('Shared edition')
            ->assertSee(__('No exact duplicates. Nicely kept!'));
    }
}
