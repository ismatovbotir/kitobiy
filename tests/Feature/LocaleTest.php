<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_interface_language_is_uzbek(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('lang="uz"', false)
            ->assertSee('Kirish');
    }

    public function test_guest_can_switch_language_for_the_session(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update', 'ru'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'ru');

        $this->get(route('login'))->assertSee('lang="ru"', false)->assertSee('Войти');
    }

    public function test_signed_in_user_language_choice_is_saved_on_the_account(): void
    {
        $user = User::factory()->create(['locale' => 'uz']);

        $this->actingAs($user)->post(route('locale.update', 'en'));

        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_saved_user_language_is_applied(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('lang="ru"', false);
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->post(route('locale.update', 'de'))->assertNotFound();
    }

    public function test_language_survives_log_out(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($user)->post(route('logout'));

        $this->get(route('login'))->assertSee('lang="ru"', false);
    }
}
