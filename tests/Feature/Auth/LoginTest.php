<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(__('Log in'));
    }

    public function test_home_sends_guests_to_login_through_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_user_can_log_in_with_correct_password(): void
    {
        $user = User::factory()->create();

        Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_log_in_with_wrong_password(): void
    {
        $user = User::factory()->create();

        Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        $component = Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password');

        foreach (range(1, 5) as $attempt) {
            $component->call('login');
        }

        $component->set('password', 'password')->call('login')->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_signed_in_user_is_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_renders_for_signed_in_user(): void
    {
        $user = User::factory()->create(['name' => 'Dilnoza']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Dilnoza');
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
