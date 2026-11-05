<?php

use App\Models\LibraryInvitation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $token = '';

    /**
     * Guests are sent back here after logging in or signing up.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        abort_if($this->invitation === null, 404);

        if (auth()->guest()) {
            session()->put('url.intended', route('invitations.show', $token));
        }
    }

    #[Computed]
    public function invitation(): ?LibraryInvitation
    {
        return LibraryInvitation::query()
            ->where('token', $this->token)
            ->with(['library', 'inviter'])
            ->first();
    }

    /**
     * Why the signed-in user cannot accept, or null when they can.
     */
    #[Computed]
    public function blockedReason(): ?string
    {
        $user = auth()->user();

        if (! $this->invitation->isUsable()) {
            return __('This invitation has expired, was revoked or was already used. Ask for a new one.');
        }

        if ($user === null) {
            return null;
        }

        if ($this->invitation->library->hasMember($user)) {
            return __('You are already a member of this library.');
        }

        return null;
    }

    public function accept(): void
    {
        abort_if(auth()->guest() || $this->blockedReason !== null, 403);

        if (! $this->invitation->accept(auth()->user())) {
            unset($this->invitation, $this->blockedReason);

            return;
        }

        session()->flash('status', __('Welcome to :library!', ['library' => $this->invitation->library->name]));

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return $this->view()
            ->layout(auth()->check() ? 'layouts::app' : 'layouts::guest')
            ->title(__('Invitation'));
    }
};
?>

<div>
    <h1 class="font-serif text-2xl font-semibold">{{ $this->invitation->library->name }}</h1>
    <p class="mt-2 text-ink-soft">
        {{ __(':inviter invites you to join this library on Kitobiy.', ['inviter' => $this->invitation->inviter->name]) }}
    </p>

    @if ($this->blockedReason)
        <p class="mt-6 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $this->blockedReason }}</p>

        @auth
            <a href="{{ route('dashboard') }}" wire:navigate class="link mt-4 inline-block">{{ __('Go to my library') }}</a>
        @endauth
    @elseauth
        <button type="button" wire:click="accept" class="btn-primary mt-6" wire:loading.attr="disabled">{{ __('Accept invitation') }}</button>
    @else
        <p class="mt-6 text-sm text-ink-soft">{{ __('Log in or sign up to accept the invitation.') }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <a href="{{ route('register') }}" wire:navigate class="btn-primary">{{ __('Sign up') }}</a>
            <a href="{{ route('login') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg border border-ink/20 px-4 py-2.5 font-semibold">{{ __('Log in') }}</a>
        </div>
    @endif
</div>
