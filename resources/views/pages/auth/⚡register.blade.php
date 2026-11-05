<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

new class extends Component {
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Create the account in the current interface language, sign in and send
     * the email confirmation link. A failed mail does not block sign-up: the
     * confirmation page can send it again.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([...$validated, 'locale' => app()->getLocale()]);
        $user->libraryOrCreateDefault();

        Auth::login($user);
        session()->regenerate();

        try {
            event(new Registered($user));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
        }

        $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
    }

    public function render()
    {
        return $this->view()->layout('layouts::guest')->title(__('Sign up'));
    }
};
?>

<div>
    <h1 class="font-serif text-2xl font-semibold">{{ __('Sign up') }}</h1>
    <p class="mt-1 text-sm text-ink-soft">{{ __('Catalog your home library and stop buying the same book twice.') }}</p>

    <form wire:submit="register" class="mt-6 space-y-4">
        <div>
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input wire:model="name" id="name" type="text" autocomplete="name" required autofocus class="form-input">
            @error('name') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input wire:model="email" id="email" type="email" autocomplete="email" required class="form-input">
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input wire:model="password" id="password" type="password" autocomplete="new-password" required class="form-input">
            @error('password') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" required class="form-input">
        </div>

        <button type="submit" class="btn-primary">{{ __('Create account') }}</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-soft">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" wire:navigate class="link">{{ __('Log in') }}</a>
    </p>
</div>
