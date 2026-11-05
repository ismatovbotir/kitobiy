<?php

use App\Models\Edition;
use App\Models\Library;
use App\Models\WishlistItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The gift page: a read-only list of a library's books for friends, opened by
 * a secret link and without an account. Only book details are shown; notes,
 * tags and members stay private.
 */
new class extends Component {
    use WithPagination;

    public const int PER_PAGE = 48;

    #[Locked]
    public string $token = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        abort_if($this->library === null, 404);
    }

    /**
     * A turned-off or renewed link stops working right away, even on a page
     * that is already open.
     */
    public function hydrate(): void
    {
        abort_if($this->library === null, 404);
    }

    #[Computed]
    public function library(): ?Library
    {
        return Library::query()->where('share_token', $this->token)->first();
    }

    /**
     * Each edition once, even when the library holds several copies.
     */
    #[Computed]
    public function editions(): LengthAwarePaginator
    {
        return Edition::query()
            ->whereIn('id', $this->library->giftPageCopies()->matching($this->search)->select('edition_id'))
            ->with('authors')
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }

    #[Computed]
    public function totalEditions(): int
    {
        return $this->library->giftPageCopies()->distinct()->count('edition_id');
    }

    /**
     * @return Collection<int, WishlistItem>
     */
    #[Computed]
    public function wishes(): Collection
    {
        return $this->library->wishlistItems()->latest('id')->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::public')
            ->title($this->library?->name ?? '');
    }
};
?>

<div>
    <div class="max-w-2xl">
        <p class="text-sm font-medium tracking-wide text-accent uppercase">{{ __('Gift page') }}</p>
        <h1 class="mt-1 font-serif text-3xl font-semibold">{{ $this->library->name }}</h1>
        <p class="mt-2 text-ink-soft">
            {{ __('These books are already on the shelves. Planning a present? Check here first, so you don’t give a book they already have.') }}
        </p>
        <p class="mt-1 text-sm text-ink-soft">{{ trans_choice(':count book|:count books', $this->totalEditions) }}</p>
    </div>

    <div class="mt-6 max-w-2xl">
        <label for="search" class="sr-only">{{ __('Search') }}</label>
        <input wire:model.live.debounce.300ms="search" id="search" type="search" autocomplete="off"
               placeholder="{{ __('Type a title, author or ISBN to check') }}" class="form-input">

        @if (trim($search) !== '')
            <p @class([
                'mt-3 rounded-lg px-4 py-3 text-sm font-medium',
                'bg-amber-50 text-amber-900' => $this->editions->total() > 0,
                'bg-emerald-50 text-emerald-900' => $this->editions->total() === 0,
            ]) role="status">
                @if ($this->editions->total() > 0)
                    {{ __('They already have this. Better pick another book.') }}
                @else
                    {{ __('Not on their shelves. It could make a good gift!') }}
                @endif
            </p>
        @endif
    </div>

    @if (trim($search) === '' && $this->editions->onFirstPage() && $this->wishes->isNotEmpty())
        <section class="mt-6 max-w-2xl rounded-xl border border-accent/30 bg-accent/5 p-4">
            <h2 class="font-serif text-xl font-semibold">{{ __('On their wishlist') }}</h2>
            <ul class="mt-2 space-y-2">
                @foreach ($this->wishes as $wish)
                    <li wire:key="wish-{{ $wish->id }}">
                        <span class="font-medium">{{ $wish->title }}</span>
                        @if ($wish->author)
                            <span class="text-sm text-ink-soft">· {{ $wish->author }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-6" wire:loading.class="opacity-60" wire:target="search">
        @if ($this->editions->isNotEmpty())
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->editions as $edition)
                    <li wire:key="edition-{{ $edition->id }}" class="flex h-full gap-3 rounded-xl border border-ink/10 bg-white p-3">
                        <x-book-cover :edition="$edition" class="w-14 shrink-0 self-start" />
                        <div class="min-w-0">
                            <p class="line-clamp-2 font-serif font-semibold leading-snug">{{ $edition->title }}</p>
                            @if ($edition->authors->isNotEmpty())
                                <p class="mt-0.5 truncate text-sm">{{ $edition->authors->pluck('name')->join(', ') }}</p>
                            @endif
                            <p class="mt-1 truncate text-xs text-ink-soft">
                                {{ collect([$edition->published_year, \App\Support\Language::name($edition->language)])->filter()->join(' · ') }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">
                {{ $this->editions->links() }}
            </div>
        @elseif (trim($search) === '')
            <div class="rounded-xl border border-dashed border-ink/20 px-6 py-12 text-center text-ink-soft">
                {{ __('No books on this list yet.') }}
            </div>
        @endif
    </div>
</div>
