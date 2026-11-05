<?php

namespace Tests\Feature;

use App\Models\Library;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_regular_user_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        Livewire::actingAs($user)->test('pages::admin.dashboard')->assertForbidden();
    }

    public function test_super_admin_sees_the_overview(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Library::factory()->ownedBy($admin)->create();
        Publisher::factory()->count(2)->create();
        Publisher::factory()->verified()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Overview'))
            ->assertSee(__(':count unverified', ['count' => 2]))
            ->assertSee(__(':count active', ['count' => 22]));
    }

    public function test_admin_link_is_shown_only_to_super_admins(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('dashboard'))->assertSee(route('admin.dashboard'));
    }

    public function test_super_admin_flag_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $user->update(['is_super_admin' => true]);

        $this->assertFalse($user->refresh()->isSuperAdmin());
    }

    public function test_command_grants_and_revokes_super_admin(): void
    {
        $user = User::factory()->create(['email' => 'botir@example.com']);

        $this->artisan('kitobiy:make-super-admin', ['email' => 'botir@example.com'])
            ->expectsOutputToContain('is now a super admin')
            ->assertSuccessful();
        $this->assertTrue($user->refresh()->isSuperAdmin());

        $this->artisan('kitobiy:make-super-admin', ['email' => 'botir@example.com', '--revoke' => true])
            ->assertSuccessful();
        $this->assertFalse($user->refresh()->isSuperAdmin());
    }

    public function test_command_fails_for_an_unknown_email(): void
    {
        $this->artisan('kitobiy:make-super-admin', ['email' => 'nobody@example.com'])
            ->expectsOutputToContain('No user with the email')
            ->assertFailed();
    }
}
