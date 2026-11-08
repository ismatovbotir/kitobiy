<?php

use App\Models\Edition;
use App\Models\Publisher;
use App\Support\SearchKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public const int PER_PAGE = 25;

    #[Locked]
    public Publisher $publisher;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /**
     * Every later request checks again, so a removed member loses access at once.
     */
    public function hydrate(): void
    {
        $this->authorize('manageBooks', $this->publisher);
    }

    public function mount(Publisher $publisher): void
    {
        $this->authorize('manageBooks', $publisher);
        $this->publisher = $publisher;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Every edition under this publisher's name, including ones readers entered
     * before the publisher joined (shown so the publisher can take them over).
     *
     * @return LengthAwarePaginator<int, Edition>
     */
    #[Computed]
    public function editions(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $isbn = Edition::normalizeIsbn($search);

        return $this->publisher->editions()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereLike('search_text', SearchKey::containsPattern($search) ?? '%')
                ->orWhere('isbn_13', $isbn)
                ->orWhere('isbn_10', $isbn)))
            ->with('authors')
            ->withCount('copies')
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function render()
    {
        return $this->view()->title($this->publisher->name);
    }
};
?>

<div>
    <a href="{{ route('publisher.index') }}" wire:navigate class="link text-sm">← {{ __('For publishers') }}</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-serif text-3xl font-semibold">{{ $publisher->name }}</h1>
        <a href="{{ route('publisher.books.create', $publisher) }}" wire:navigate class="btn-primary w-auto">{{ __('Add a book') }}</a>
    </div>
    <p class="mt-2 text-ink-soft">{{ __('Books you add here are found by every reader who scans their barcode. Readers cannot change them.') }}</p>

    <x-flash class="mt-4" />

    <div class="mt-6">
        <label for="search" class="sr-only">{{ __('Search') }}</label>
        <input wire:model.live.debounce.300ms="search" id="search" type="search" placeholder="{{ __('Title or ISBN') }}" class="form-input sm:max-w-sm">
    </div>

    @if ($this->editions->isEmpty())
        <p class="mt-6 rounded-xl border border-dashed border-ink/20 px-4 py-8 text-center text-ink-soft">
            {{ $search !== '' ? __('Nothing found.') : __('No books yet. Add the first one.') }}
        </p>
    @else
        <ul class="mt-4 divide-y divide-ink/10 rounded-xl border border-ink/10 bg-white">
            @foreach ($this->editions as $edition)
                <li wire:key="edition-{{ $edition->id }}">
                    <a href="{{ route('publisher.books.edit', [$publisher, $edition]) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 hover:bg-paper/60">
                        <x-book-cover :edition="$edition" class="h-14 w-10 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $edition->title }}</p>
                            <p class="truncate text-sm text-ink-soft">
                                {{ collect([$edition->authors->pluck('name')->join(', '), $edition->published_year, $edition->isbn_13 ?? $edition->isbn_10])->filter()->join(' · ') }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right text-xs">
                            @unless ($edition->isFromPublisher())
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800">{{ __('Entered by readers') }}</span>
                            @endunless
                            <p class="mt-1 text-ink-soft">{{ trans_choice('In :count library|In :count libraries', $edition->copies_count) }}</p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $this->editions->links() }}</div>
    @endif
</div>
