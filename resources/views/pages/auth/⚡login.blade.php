<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * Sign the user in, allowing 5 tries per email + IP per minute.
     */
    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout(request()));

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
    }

    public function render()
    {
        return $this->view()->layout('layouts::guest')->title(__('Log in'));
    }
};
?>

<div>
    <h1 class="font-serif text-2xl font-semibold">{{ __('Log in') }}</h1>
    <p class="mt-1 text-sm text-ink-soft">{{ __('Welcome back to your home library.') }}</p>

    <x-flash class="mt-4" />

    <form wire:submit="login" class="mt-6 space-y-4">
        <div>
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input wire:model="email" id="email" type="email" autocomplete="email" required autofocus class="form-input">
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label for="password" class="form-label">{{ __('Password') }}</label>
                <a href="{{ route('password.request') }}" wire:navigate class="link text-sm">{{ __('Forgot your password?') }}</a>
            </div>
            <input wire:model="password" id="password" type="password" autocomplete="current-password" required class="form-input">
            @error('password') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input wire:model="remember" type="checkbox" class="rounded border-ink/30 text-accent">
            {{ __('Remember me') }}
        </label>

        <button type="submit" class="btn-primary">{{ __('Log in') }}</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-soft">
        {{ __('No account yet?') }}
        <a href="{{ route('register') }}" wire:navigate class="link">{{ __('Sign up') }}</a>
    </p>
</div>
