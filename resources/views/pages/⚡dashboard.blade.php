<?php

use App\Models\Copy;
use App\Models\Library;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    private const int GIFT_PAGE_PROMPT_MIN_BOOKS = 5;

    #[Computed]
    public function library(): Library
    {
        return auth()->user()->libraryOrCreateDefault()->loadCount(['members', 'copies']);
    }

    /**
     * @return Collection<int, Copy>
     */
    #[Computed]
    public function recentCopies(): Collection
    {
        return $this->library->copies()->with('edition.authors')->latest('id')->limit(6)->get();
    }

    /**
     * How many editions this library holds more than one copy of.
     */
    #[Computed]
    public function duplicateCount(): int
    {
        return $this->library->duplicateEditions()->count();
    }

    /**
     * Suggest the gift page to those who can turn it on, once there is a list worth sharing.
     */
    #[Computed]
    public function suggestsGiftPage(): bool
    {
        return $this->library->copies_count >= self::GIFT_PAGE_PROMPT_MIN_BOOKS
            && ! $this->library->isShared()
            && auth()->user()->can('share', $this->library);
    }

    public function render()
    {
        return $this->view()->title(__('My library'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <h1 class="font-serif text-3xl font-semibold">{{ $this->library->name }}</h1>
    <p class="mt-2 text-ink-soft">
        {{ trans_choice(':count member|:count members', $this->library->members_count) }}
        · <a href="{{ route('library.members') }}" wire:navigate class="link">{{ __('Members and invitations') }}</a>
    </p>

    <div class="mt-6 grid gap-3 sm:grid-cols-3">
        @can('addBooks', $this->library)
            <a href="{{ route('books.create') }}" wire:navigate class="btn-primary h-full py-4 text-lg">+ {{ __('Add a book') }}</a>
        @endcan
        <a href="{{ route('books.index') }}" wire:navigate class="rounded-xl border border-ink/10 bg-white px-4 py-3 transition hover:border-accent/40">
            <span class="block font-serif text-2xl font-semibold">{{ $this->library->copies_count }}</span>
            <span class="text-sm text-ink-soft">{{ __('books in the library') }}</span>
        </a>
        <a href="{{ route('books.duplicates') }}" wire:navigate class="rounded-xl border border-ink/10 bg-white px-4 py-3 transition hover:border-accent/40">
            <span @class(['block font-serif text-2xl font-semibold', 'text-red-700' => $this->duplicateCount > 0])>{{ $this->duplicateCount }}</span>
            <span class="text-sm text-ink-soft">{{ __('books you own twice') }}</span>
        </a>
    </div>

    @if ($this->suggestsGiftPage)
        {{-- "Not now" is remembered per browser and library; without storage the prompt simply comes back. --}}
        <div x-data="{
                 dismissed: false,
                 storageKey: 'kitobiy.gift-prompt-dismissed.{{ $this->library->id }}',
                 init() {
                     try { this.dismissed = localStorage.getItem(this.storageKey) === '1'; } catch {}
                 },
                 dismiss() {
                     this.dismissed = true;
                     try { localStorage.setItem(this.storageKey, '1'); } catch {}
                 },
             }"
             x-show="! dismissed" x-cloak
             class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-accent/30 bg-accent/5 px-4 py-3">
            <p class="min-w-0 grow basis-64 text-sm">{{ __('Let friends see your books so they can choose the right gift.') }}</p>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('library.members') }}#gift-page" class="link">{{ __('See how it works') }}</a>
                <button type="button" x-on:click="dismiss" class="min-h-11 cursor-pointer text-ink-soft hover:text-ink">{{ __('Not now') }}</button>
            </div>
        </div>
    @endif

    @if ($this->recentCopies->isEmpty())
        <div class="mt-8 rounded-xl border border-dashed border-ink/20 px-6 py-12 text-center">
            <p class="text-ink-soft">{{ __('No books yet. Grab the nearest shelf and add the first one!') }}</p>
        </div>
    @else
        <div class="mt-10 flex items-baseline justify-between gap-4">
            <h2 class="font-serif text-xl font-semibold">{{ __('Recently added') }}</h2>
            <a href="{{ route('books.index') }}" wire:navigate class="link text-sm">{{ __('All books') }} →</a>
        </div>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->recentCopies as $copy)
                <li wire:key="copy-{{ $copy->id }}">
                    <x-book-card :copy="$copy" />
                </li>
            @endforeach
        </ul>
    @endif
</div>
