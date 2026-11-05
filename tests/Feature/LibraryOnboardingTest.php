<?php

namespace Tests\Feature;

use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_the_default_library(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Botir Ismatov')
            ->set('email', 'botir@example.com')
            ->set('password', 'password-123')
            ->set('password_confirmation', 'password-123')
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('email', 'botir@example.com')->sole();
        $library = $user->currentLibrary();

        $this->assertSame('Botirning kutubxonasi', $library->name);
        $this->assertTrue($library->isOwnedBy($user));
        $this->assertFalse($library->is_public);
    }

    public function test_user_without_library_gets_one_on_the_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Malika']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Malikaning kutubxonasi');

        $this->assertTrue($user->currentLibrary()->isOwnedBy($user));
    }

    public function test_default_library_is_created_only_once(): void
    {
        $user = User::factory()->create();

        $first = $user->libraryOrCreateDefault();
        $second = $user->libraryOrCreateDefault();

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('libraries', 1);
    }

    public function test_existing_library_is_used_instead_of_a_default_one(): void
    {
        $user = User::factory()->create();
        $library = Library::factory()->ownedBy($user)->create(['name' => 'Karimovlar kutubxonasi']);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Karimovlar kutubxonasi');

        $this->assertDatabaseCount('libraries', 1);
        $this->assertTrue($user->currentLibrary()->is($library));
    }

    public function test_default_library_name_follows_the_interface_language(): void
    {
        $user = User::factory()->make(['name' => 'Botir Ismatov']);

        app()->setLocale('ru');
        $this->assertSame('Библиотека Botir', $user->defaultLibraryName());

        app()->setLocale('en');
        $this->assertSame('Botir’s library', $user->defaultLibraryName());
    }

    public function test_slug_falls_back_when_the_name_has_no_latin_letters(): void
    {
        $library = User::factory()->create()->createLibrary('📚');

        $this->assertStringStartsWith('library-', $library->slug);
    }
}
