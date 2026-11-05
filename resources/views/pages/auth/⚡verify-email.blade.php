<?php

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

new class extends Component {
    /**
     * Verified users have nothing to do here.
     */
    public function mount(): void
    {
        if (auth()->user()->hasVerifiedEmail()) {
            $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
        }
    }

    /**
     * Send the confirmation link again, at most 3 times per 10 minutes per user.
     */
    public function resend(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(route('dashboard', absolute: false), navigate: true);

            return;
        }

        $throttleKey = 'verify-email:'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $this->addError('resend', __('Too many emails. Please try again in :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
            ]));

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            $this->addError('resend', __('We could not send the email right now. Please try again later.'));

            return;
        }

        session()->flash('status', __('We have sent a new confirmation link to :email.', ['email' => $user->email]));
    }

    public function render()
    {
        return $this->view()->layout('layouts::guest')->title(__('Confirm your email'));
    }
};
?>

<div>
    <h1 class="font-serif text-2xl font-semibold">{{ __('Confirm your email') }}</h1>
    <p class="mt-2 text-sm text-ink-soft">
        {{ __('We sent a link to :email. Open it to start using Kitobiy.', ['email' => auth()->user()->email]) }}
    </p>
    <p class="mt-2 text-sm text-ink-soft">{{ __('No email? Check the spam folder, or send it again.') }}</p>

    <x-flash class="mt-4" />
    @error('resend') <p class="form-error mt-4">{{ $message }}</p> @enderror

    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
        <button type="button" wire:click="resend" wire:loading.attr="disabled" class="btn-primary">
            {{ __('Send the link again') }}
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link text-sm">{{ __('Log out') }}</button>
        </form>
    </div>
</div>
