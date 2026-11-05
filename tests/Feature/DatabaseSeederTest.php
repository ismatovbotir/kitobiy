<?php

namespace Tests\Feature;

use App\Models\Library;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_demo_library_with_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $library = Library::where('slug', DatabaseSeeder::DEMO_LIBRARY_SLUG)->firstOrFail();
        $this->assertTrue($library->isOwnedBy(User::where('email', 'test@example.com')->firstOrFail()));
        $this->assertSame(2, $library->members()->count());
        $this->assertSame(15, $library->copies()->count());
        $this->assertSame(["O'tkan kunlar"], $library->duplicateEditions()->pluck('title')->all());
        $this->assertSame(['Мастер и Маргарита'], $library->sameWorkDuplicates()->pluck('original_title')->all());
    }

    public function test_running_twice_does_not_fail_or_duplicate_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('libraries', 1);
        $this->assertDatabaseCount('copies', 15);
    }
}
