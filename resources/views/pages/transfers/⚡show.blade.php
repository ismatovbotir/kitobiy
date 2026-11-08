<?php

use App\Models\CopyTransfer;
use App\Models\Library;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Where a book's new owner lands from the giver's link. The book details and
 * the giver's name show only while the link works, and never the giver's notes.
 */
new class extends Component
{
    #[Locked]
    public string $token = '';

    public string $libraryId = '';

    /**
     * Guests and unconfirmed users are sent back here once they can accept.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        abort_if($this->transfer === null, 404);

        $user = auth()->user();

        if ($user === null || $user->needsConfirmation()) {
            session()->put('url.intended', route('transfers.show', $token));
        }

        if ($user !== null) {
            $user->libraryOrCreateDefault();

            $current = $user->currentLibrary();
            $this->libraryId = (string) ($this->targetLibraries->firstWhere('id', $current?->id) ?? $this->targetLibraries->first())?->id;
        }
    }

    #[Computed]
    public function transfer(): ?CopyTransfer
    {
        return CopyTransfer::query()
            ->where('token', $this->token)
            ->with(['copy.edition.authors', 'copy.library', 'offeredBy'])
            ->first();
    }

    /**
     * The signed-in user's libraries that can take the book.
     *
     * @return Collection<int, Library>
     */
    #[Computed]
    public function targetLibraries(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return new Collection;
        }

        return $user->libraries()
            ->whereKeyNot($this->transfer->copy->library_id)
            ->orderBy('name')
            ->get()
            ->filter(fn (Library $library): bool => $user->can('addBooks', $library))
            ->values();
    }

    /**
     * Why the signed-in user cannot accept, or null when they can.
     */
    #[Computed]
    public function blockedReason(): ?string
    {
        $user = auth()->user();

        if (! $this->transfer->isUsable()) {
            return __('This link has expired, was cancelled or was already used. Ask for a new one.');
        }

        if ($user === null) {
            return null;
        }

        if ($user->needsConfirmation()) {
            return __('Confirm your account in Telegram first, then open this link again.');
        }

        if ($this->targetLibraries->isEmpty()) {
            return __('You need another library where you can add books.');
        }

        return null;
    }

    public function accept(): void
    {
        abort_if(auth()->guest() || $this->blockedReason !== null, 403);

        $library = $this->targetLibraries->firstWhere('id', (int) $this->libraryId);

        if ($library === null) {
            $this->addError('libraryId', __('Choose one of your libraries.'));

            return;
        }

        if (! $this->transfer->accept(auth()->user(), $library)) {
            unset($this->transfer, $this->blockedReason, $this->targetLibraries);

            return;
        }

        session()->flash('status', __('“:title” is now in :library.', [
            'title' => $this->transfer->copy->edition->title,
            'library' => $library->name,
        ]));

        $this->redirectRoute('books.show', $this->transfer->copy_id, navigate: true);
    }

    public function render()
    {
        return $this->view()
            ->layout(auth()->check() ? 'layouts::app' : 'layouts::guest')
            ->title(__('A book for you'));
    }
};
?>

<div class="max-w-2xl">
    <h1 class="font-serif text-2xl font-semibold">{{ __('A book for you') }}</h1>

    @if ($this->transfer->isUsable())
        @php($copy = $this->transfer->copy)
        <p class="mt-2 text-ink-soft">
            {{ __(':name wants to give you this book from “:library”.', ['name' => $this->transfer->offeredBy?->name ?? __('Someone'), 'library' => $copy->library->name]) }}
        </p>

        <section class="mt-4 flex gap-4 rounded-xl border border-ink/10 bg-white p-4">
            <div class="w-20 shrink-0 self-start">
                <x-book-cover :edition="$copy->edition" class="w-full" />
            </div>
            <div class="min-w-0">
                <p class="font-serif text-xl font-semibold leading-tight">{{ $copy->edition->title }}</p>
                @if ($copy->edition->authors->isNotEmpty())
                    <p class="mt-1 text-sm">{{ $copy->edition->authors->pluck('name')->join(', ') }}</p>
                @endif
                @if ($copy->condition)
                    <p class="mt-2 text-sm text-ink-soft">{{ __('Condition') }}: {{ $copy->condition->label() }}</p>
                @endif
            </div>
        </section>
    @endif

    @if ($this->blockedReason)
        <p class="mt-6 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $this->blockedReason }}</p>

        @auth
            <a href="{{ route('dashboard') }}" wire:navigate class="link mt-4 inline-block">{{ __('Go to my library') }}</a>
        @endauth
    @elseauth
        <form wire:submit="accept" class="mt-6 space-y-4">
            <div>
                <label for="libraryId" class="form-label">{{ __('Add it to') }}</label>
                <select wire:model="libraryId" id="libraryId" class="form-input">
                    @foreach ($this->targetLibraries as $library)
                        <option wire:key="library-{{ $library->id }}" value="{{ $library->id }}">{{ $library->name }}</option>
                    @endforeach
                </select>
                @error('libraryId') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Accept the book') }}</button>
        </form>
    @else
        <p class="mt-6 text-sm text-ink-soft">{{ __('Log in or sign up to accept the book.') }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <a href="{{ route('register') }}" wire:navigate class="btn-primary">{{ __('Sign up') }}</a>
            <a href="{{ route('login') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg border border-ink/20 px-4 py-2.5 font-semibold">{{ __('Log in') }}</a>
        </div>
    @endif
</div>
