<?php

use App\Services\Telegram;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component {
    /** The one-time t.me link; it holds a secret, so it is shown only to this user. */
    public string $telegramLink = '';

    /**
     * Confirmed users have nothing to do here. Everyone else gets a fresh bot link.
     */
    public function mount(): void
    {
        if (! auth()->user()->needsConfirmation()) {
            $this->redirectIntended(route('dashboard', absolute: false), navigate: true);

            return;
        }

        $this->newLink();
    }

    /**
     * A new link replaces the old one, at most 10 times per 10 minutes per user.
     */
    public function newLink(): void
    {
        $throttleKey = 'confirm-account:'.auth()->id();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $this->addError('link', __('Too many links. Please try again in :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
            ]));

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $this->telegramLink = auth()->user()->newTelegramLink(app(Telegram::class)->botUsername());
    }

    /**
     * Polled while the page is open: moves on as soon as the bot has confirmed the account.
     */
    public function checkConfirmation(): void
    {
        if (auth()->user()->fresh()->isConfirmed()) {
            session()->flash('status', __('Your account is confirmed. Welcome to Kitobiy!'));
            $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts::guest')->title(__('Confirm your account'));
    }
};
?>

<div wire:poll.3s="checkConfirmation">
    <h1 class="font-serif text-2xl font-semibold">{{ __('Confirm your account') }}</h1>
    <p class="mt-2 text-sm text-ink-soft">{{ __('One last step: open our Telegram bot and press Start. This page moves on by itself.') }}</p>
    <p class="mt-2 text-sm text-ink-soft">{{ __('The link works for 15 minutes. You will also get notices about your books there.') }}</p>

    @error('link') <p class="form-error mt-4">{{ $message }}</p> @enderror

    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
        @if ($telegramLink !== '')
            <a href="{{ $telegramLink }}" target="_blank" rel="noopener" class="btn-primary sm:w-auto">{{ __('Open the bot') }}</a>
        @endif

        <button type="button" wire:click="newLink" wire:loading.attr="disabled" class="link text-sm">{{ __('Get a new link') }}</button>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-right">
        @csrf
        <button type="submit" class="link text-sm">{{ __('Log out') }}</button>
    </form>
</div>
