<?php

use App\Models\CollectorProfile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A collector's page, opened by a secret link and without an account. It shows
 * only what the collector typed for it, never their account name or email.
 */
new class extends Component
{
    #[Locked]
    public string $token = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        abort_if($this->profile === null, 404);
    }

    /**
     * A turned-off link stops working right away, even on a page that is already open.
     */
    public function hydrate(): void
    {
        abort_if($this->profile === null, 404);
    }

    #[Computed]
    public function profile(): ?CollectorProfile
    {
        return CollectorProfile::query()->where('share_token', $this->token)->first();
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::public')
            ->title($this->profile?->display_name ?? '');
    }
};
?>

<div class="max-w-2xl">
    <p class="text-sm font-medium tracking-wide text-accent uppercase">{{ __('Book collector') }}</p>
    <h1 class="mt-1 font-serif text-3xl font-semibold">{{ $this->profile->display_name }}</h1>
    @if (filled($this->profile->bio))
        <p class="mt-3 whitespace-pre-line text-ink-soft">{{ $this->profile->bio }}</p>
    @endif
</div>
