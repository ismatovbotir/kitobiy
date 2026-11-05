<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_new_user_can_register_and_is_signed_in(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Aziz')
            ->set('email', 'aziz@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::firstWhere('email', 'aziz@example.com');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('uz', $user->locale);
    }

    public function test_new_user_keeps_the_language_chosen_before_signing_up(): void
    {
        $this->withSession(['locale' => 'ru'])->get(route('register'));
        app()->setLocale('ru');

        Livewire::test('pages::auth.register')
            ->set('name', 'Olga')
            ->set('email', 'olga@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register');

        $this->assertSame('ru', User::firstWhere('email', 'olga@example.com')->locale);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::test('pages::auth.register')
            ->set('name', 'Aziz')
            ->set('email', 'taken@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertGuest();
    }

    public function test_password_must_be_confirmed(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Aziz')
            ->set('email', 'aziz@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'different')
            ->call('register')
            ->assertHasErrors(['password' => 'confirmed']);
    }
}
