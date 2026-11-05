<?php

use Illuminate\Support\Facades\Password;
use Livewire\Component;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

new class extends Component {
    public string $email = '';

    /**
     * Send a reset link. The same message is shown whether or not the email
     * exists, so the form can't be used to discover accounts.
     */
    public function sendResetLink(): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        try {
            Password::sendResetLink(['email' => $this->email]);
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            $this->addError('email', __('We could not send the email right now. Please try again later.'));

            return;
        }

        session()->flash('status', __('If an account exists for this email, we have sent a password reset link.'));
    }

    public function render()
    {
        return $this->view()->layout('layouts::guest')->title(__('Forgot your password?'));
    }
};
?>

<div>
    <h1 class="font-serif text-2xl font-semibold">{{ __('Forgot your password?') }}</h1>
    <p class="mt-1 text-sm text-ink-soft">{{ __('Enter your email and we will send you a link to choose a new password.') }}</p>

    <x-flash class="mt-4" />

    <form wire:submit="sendResetLink" class="mt-6 space-y-4">
        <div>
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input wire:model="email" id="email" type="email" autocomplete="email" required autofocus class="form-input">
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary">{{ __('Send reset link') }}</button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" wire:navigate class="link">{{ __('Back to log in') }}</a>
    </p>
</div>
