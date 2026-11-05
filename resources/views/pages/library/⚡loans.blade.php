<?php

use App\Models\Library;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Who has this library's books, and which books it has borrowed. Soonest due
 * come first; loans without a date come last.
 */
new class extends Component
{
    public function mount(): void
    {
        $this->authorize('view', $this->library);
    }

    #[Computed]
    public function library(): Library
    {
        return auth()->user()->libraryOrCreateDefault();
    }

    /**
     * @return Collection<int, Loan>
     */
    #[Computed]
    public function lentOut(): Collection
    {
        return Loan::query()
            ->active()
            ->whereHas('copy', fn (Builder $copy) => $copy->where('library_id', $this->library->id))
            ->with('copy.edition')
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->get();
    }

    /**
     * @return Collection<int, Loan>
     */
    #[Computed]
    public function borrowed(): Collection
    {
        return Loan::query()
            ->active()
            ->where('borrower_library_id', $this->library->id)
            ->with(['copy.edition.authors', 'copy.library'])
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->get();
    }

    public function render()
    {
        return $this->view()->title(__('Loans'));
    }
};
?>

<div class="max-w-2xl">
    <h1 class="font-serif text-2xl font-semibold">{{ __('Loans') }}</h1>

    <x-flash class="mt-4" />

    <section class="mt-6">
        <h2 class="font-serif text-xl font-semibold">{{ __('Lent out') }}</h2>
        @if ($this->lentOut->isEmpty())
            <p class="mt-2 text-sm text-ink-soft">{{ __('No books are lent out. To lend one, open it in your library.') }}</p>
        @else
            <ul class="mt-2 divide-y divide-ink/10 rounded-xl border border-ink/10 bg-white">
                @foreach ($this->lentOut as $loan)
                    <li wire:key="lent-{{ $loan->id }}" class="px-4 py-3 text-sm">
                        <a href="{{ route('books.show', $loan->copy) }}" wire:navigate class="link font-medium">{{ $loan->copy->edition->title }}</a>
                        <p class="text-ink-soft">
                            {{ $loan->borrower_name }}
                            @can('viewBorrowerPhone', $this->library)
                                @if ($loan->borrower_phone) · {{ $loan->borrower_phone }} @endif
                            @endcan
                        </p>
                        <x-loan-due :loan="$loan" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="font-serif text-xl font-semibold">{{ __('Borrowed') }}</h2>
        @if ($this->borrowed->isEmpty())
            <p class="mt-2 text-sm text-ink-soft">{{ __('Nothing borrowed from other libraries.') }}</p>
        @else
            <ul class="mt-2 divide-y divide-ink/10 rounded-xl border border-ink/10 bg-white">
                @foreach ($this->borrowed as $loan)
                    <li wire:key="borrowed-{{ $loan->id }}" class="px-4 py-3 text-sm">
                        <p class="font-medium">{{ $loan->copy->edition->title }}</p>
                        <p class="text-ink-soft">{{ __('Borrowed from :library', ['library' => $loan->copy->library->name]) }}</p>
                        <x-loan-due :loan="$loan" />
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-ink-soft">{{ __('The owner marks a book as returned when they get it back.') }}</p>
        @endif
    </section>
</div>
