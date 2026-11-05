<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_to_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::test('pages::auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendResetLink')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_answer_without_an_error(): void
    {
        Notification::fake();

        Livewire::test('pages::auth.forgot-password')
            ->set('email', 'nobody@example.com')
            ->call('sendResetLink')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_mail_failure_shows_an_error_instead_of_crashing(): void
    {
        $this->useFailingMailer();
        $user = User::factory()->create();

        Livewire::test('pages::auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendResetLink')
            ->assertHasErrors('email')
            ->assertDontSee(__('If an account exists for this email, we have sent a password reset link.'));
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::test('pages::auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
                ->assertOk();

            Livewire::test('pages::auth.reset-password', ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'new-secret-password')
                ->set('password_confirmation', 'new-secret-password')
                ->call('resetPassword')
                ->assertHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('new-secret-password', $user->refresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::test('pages::auth.reset-password', ['token' => 'bogus'])
            ->set('email', $user->email)
            ->set('password', 'new-secret-password')
            ->set('password_confirmation', 'new-secret-password')
            ->call('resetPassword')
            ->assertHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
