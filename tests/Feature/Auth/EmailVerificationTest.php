<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_sends_the_confirmation_link(): void
    {
        Notification::fake();

        Livewire::test('pages::auth.register')
            ->set('name', 'Aziz')
            ->set('email', 'aziz@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasNoErrors();

        $user = User::firstWhere('email', 'aziz@example.com');

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_failing_mail_server_does_not_block_sign_up(): void
    {
        $this->useFailingMailer();

        Livewire::test('pages::auth.register')
            ->set('name', 'Aziz')
            ->set('email', 'aziz@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_unverified_users_are_sent_to_the_confirmation_page(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('books.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee($user->email);
    }

    public function test_unverified_super_admins_cannot_open_admin(): void
    {
        $user = User::factory()->unverified()->superAdmin()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_verified_users_skip_the_confirmation_page(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::auth.verify-email')->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_the_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('status');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_with_a_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_is_limited_to_three_mails(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $page = Livewire::test('pages::auth.verify-email');

        foreach (range(1, 3) as $attempt) {
            $page->call('resend')->assertHasNoErrors();
        }

        $page->call('resend')->assertHasErrors('resend');

        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_resend_reports_a_failing_mail_server(): void
    {
        $this->useFailingMailer();
        $this->actingAs(User::factory()->unverified()->create());

        Livewire::test('pages::auth.verify-email')->call('resend')->assertHasErrors('resend');
    }

    public function test_the_mail_goes_out_in_the_users_language(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['locale' => 'ru']);
        $this->actingAs($user);

        Livewire::test('pages::auth.verify-email')->call('resend');

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification, array $channels, User $notifiable, ?string $locale): bool => $locale === 'ru',
        );
    }
}
