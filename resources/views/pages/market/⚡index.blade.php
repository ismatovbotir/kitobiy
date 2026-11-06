<?php

use App\Enums\ListingKind;
use App\Models\Listing;
use App\Support\Concerns\RequiresMarket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Books people chose to offer. Only signed-in members see it, search engines
 * never do, and it shows single books, never anyone's library.
 */
new class extends Component
{
    use RequiresMarket, WithPagination;

    public const int PER_PAGE = 24;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $kind = '';

    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        return Listing::query()
            ->visibleTo(auth()->user())
            ->when(ListingKind::tryFrom($this->kind), fn (Builder $query, ListingKind $kind) => $query->where('kind', $kind))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->whereHas('copy', fn (Builder $copy) => $copy->matching($this->search)))
            ->with(['copy.edition.authors', 'seller.collectorProfile'])
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedKind(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return $this->view()->title(__('Market'));
    }
};
?>

<div>
    <h1 class="font-serif text-2xl font-semibold">{{ __('Market') }}</h1>
    <p class="mt-2 max-w-2xl text-sm text-ink-soft">{{ __('Books other members offer for sale or swap. Kitobiy only connects people: you agree on the price and the meeting yourselves.') }}</p>

    <x-flash class="mt-4" />

    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
        <div>
            <label for="search" class="sr-only">{{ __('Search') }}</label>
            <input wire:model.live.debounce.300ms="search" id="search" type="search" autocomplete="off" placeholder="{{ __('Search by title, author or ISBN') }}" class="form-input">
        </div>
        <div>
            <label for="kind" class="sr-only">{{ __('Type') }}</label>
            <select wire:model.live="kind" id="kind" class="form-input">
                <option value="">{{ __('Sale and swap') }}</option>
                @foreach (ListingKind::cases() as $listingKind)
                    <option wire:key="kind-{{ $listingKind->value }}" value="{{ $listingKind->value }}">{{ $listingKind->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($this->listings->isEmpty())
        <p class="mt-6 rounded-xl border border-ink/10 bg-white px-4 py-6 text-center text-ink-soft">{{ __('No books on offer yet. To offer one of yours, open it in your library.') }}</p>
    @else
        <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->listings as $listing)
                <li wire:key="listing-{{ $listing->id }}"><x-listing-card :listing="$listing" /></li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $this->listings->links() }}</div>
    @endif
</div>
