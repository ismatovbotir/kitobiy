<?php

use App\Models\Edition;
use App\Models\Library;
use App\Models\Work;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public function mount(): void
    {
        $library = $this->library;

        $this->authorize('view', $library);
    }

    #[Computed]
    public function library(): Library
    {
        return auth()->user()->libraryOrCreateDefault();
    }

    /**
     * Editions with more than one copy here, each with those copies.
     *
     * @return Collection<int, Edition>
     */
    #[Computed]
    public function exactDuplicates(): Collection
    {
        return $this->library->duplicateEditions()->load([
            'authors',
            'copies' => fn ($query) => $query->whereBelongsTo($this->library),
        ]);
    }

    /**
     * Works held here in more than one edition, with those editions' copies.
     *
     * @return Collection<int, Work>
     */
    #[Computed]
    public function sameWorkDuplicates(): Collection
    {
        return $this->library->sameWorkDuplicates()->load([
            'editions.authors',
            'editions.copies' => fn ($query) => $query->whereBelongsTo($this->library),
        ]);
    }

    public function render()
    {
        return $this->view()->title(__('Duplicates'));
    }
};
?>

<div class="max-w-3xl">
    <h1 class="font-serif text-3xl font-semibold">{{ __('Duplicates') }}</h1>
    <p class="mt-1 text-ink-soft">{{ __('Books you own more than once. A good list to give away or swap.') }}</p>

    <section class="mt-8">
        <h2 class="font-serif text-xl font-semibold">{{ __('Same edition') }}</h2>
        @if ($this->exactDuplicates->isEmpty())
            <p class="mt-2 text-sm text-ink-soft">{{ __('No exact duplicates. Nicely kept!') }}</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($this->exactDuplicates as $edition)
                    <li wire:key="edition-{{ $edition->id }}" class="flex gap-3 rounded-xl border border-ink/10 bg-white p-3">
                        <x-book-cover :edition="$edition" class="w-12 shrink-0 self-start" />
                        <div class="min-w-0">
                            <p class="font-serif font-semibold">{{ $edition->title }}</p>
                            <p class="text-sm text-ink-soft">{{ $edition->authors->pluck('name')->join(', ') }}</p>
                            <p class="mt-1 text-sm">
                                <span class="font-medium text-red-700">{{ trans_choice(':count copy|:count copies', $edition->copies_count) }}</span>:
                                @foreach ($edition->copies as $copy)
                                    <a wire:key="copy-{{ $copy->id }}" href="{{ route('books.show', $copy) }}" wire:navigate class="link">{{ __('Copy :number', ['number' => $loop->iteration]) }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="mt-10">
        <h2 class="font-serif text-xl font-semibold">{{ __('Same book, different editions') }}</h2>
        <p class="mt-1 text-sm text-ink-soft">{{ __('For example the original and a translation. Link editions on a book’s page.') }}</p>
        @if ($this->sameWorkDuplicates->isEmpty())
            <p class="mt-2 text-sm text-ink-soft">{{ __('Nothing here yet.') }}</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($this->sameWorkDuplicates as $work)
                    <li wire:key="work-{{ $work->id }}" class="rounded-xl border border-ink/10 bg-white p-3">
                        <ul class="divide-y divide-ink/10">
                            @foreach ($work->editions as $edition)
                                @foreach ($edition->copies as $copy)
                                    <li wire:key="work-copy-{{ $copy->id }}" class="flex items-center gap-3 py-2">
                                        <x-book-cover :edition="$edition" class="w-10 shrink-0" />
                                        <div class="min-w-0">
                                            <a href="{{ route('books.show', $copy) }}" wire:navigate class="link font-serif">{{ $edition->title }}</a>
                                            <p class="truncate text-sm text-ink-soft">
                                                {{ collect([$edition->authors->pluck('name')->join(', '), \App\Support\Language::name($edition->language)])->filter()->join(' · ') }}
                                            </p>
                                        </div>
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
