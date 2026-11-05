<?php

use App\Models\Author;
use App\Models\Edition;
use App\Models\Genre;
use App\Models\Library;
use App\Models\Loan;
use App\Support\Language;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public const int PER_PAGE = 24;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Id of a person (author, translator…) whose books are shown. */
    #[Url(except: '')]
    public string $person = '';

    #[Url(except: '')]
    public string $genre = '';

    #[Url(except: '')]
    public string $tag = '';

    #[Url(except: '')]
    public string $language = '';

    /** Only the books kept off the gift page. */
    #[Url(except: false)]
    public bool $hidden = false;

    /** 'newest' or 'title'. */
    #[Url(except: 'newest')]
    public string $sort = 'newest';

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

    #[Computed]
    public function tags(): Collection
    {
        return $this->library->tags()->orderBy('name')->get();
    }

    /**
     * Languages of the books in this library, for the filter.
     *
     * @return array<string, string> code => name
     */
    #[Computed]
    public function languages(): array
    {
        return Language::sortedNames(
            Edition::query()
                ->whereHas('copies', fn ($copies) => $copies->whereBelongsTo($this->library))
                ->whereNotNull('language')
                ->distinct()
                ->pluck('language'),
        );
    }

    /**
     * Genres of the books in this library, for the filter.
     *
     * @return array<string, string> slug => name
     */
    #[Computed]
    public function genres(): array
    {
        return Genre::query()
            ->whereHas('editions.copies', fn ($copies) => $copies->whereBelongsTo($this->library))
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Genre $genre): array => [$genre->slug => $genre->name])
            ->all();
    }

    #[Computed]
    public function selectedPerson(): ?Author
    {
        return ctype_digit($this->person) ? Author::find((int) $this->person) : null;
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->person !== '' || $this->genre !== '' || $this->tag !== '' || $this->language !== '' || $this->hidden;
    }

    #[Computed]
    public function copies(): LengthAwarePaginator
    {
        return $this->library->copies()
            ->select('copies.*')
            ->matching($this->search)
            ->when($this->selectedPerson, fn ($query, Author $person) => $query->whereHas(
                'edition.contributors', fn ($people) => $people->whereKey($person->id),
            ))
            ->when($this->genre !== '', fn ($query) => $query->whereHas(
                'edition.genres', fn ($genres) => $genres->where('genres.slug', $this->genre),
            ))
            ->when(ctype_digit($this->tag), fn ($query) => $query->whereHas(
                'tags', fn ($tags) => $tags->whereKey((int) $this->tag),
            ))
            ->when($this->language === 'none', fn ($query) => $query->whereHas('edition', fn ($edition) => $edition->whereNull('language')))
            ->when(Language::isValid($this->language), fn ($query) => $query->whereHas(
                'edition', fn ($edition) => $edition->where('language', $this->language),
            ))
            ->when($this->hidden, fn ($query) => $query->whereIn(
                'edition_id', $this->library->copiesHiddenFromGiftPage()->select('edition_id'),
            ))
            ->when(
                $this->sort === 'title',
                fn ($query) => $query
                    ->join('editions', 'editions.id', '=', 'copies.edition_id')
                    ->orderBy('editions.title')
                    ->orderBy('copies.id'),
                fn ($query) => $query->latest('copies.id'),
            )
            ->with('edition.authors')
            ->paginate(self::PER_PAGE);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'person', 'genre', 'tag', 'language', 'hidden', 'sort'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Books this library has on loan from others. They are not its own, so they
     * don't count as books here and their owner's details stay private.
     *
     * @return Collection<int, Loan>
     */
    #[Computed]
    public function borrowedBooks(): Collection
    {
        return Loan::query()
            ->active()
            ->where('borrower_library_id', $this->library->id)
            ->with(['copy.edition', 'copy.library'])
            ->latest('id')
            ->get();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'person', 'genre', 'tag', 'language', 'hidden');
        $this->resetPage();
    }

    public function render()
    {
        return $this->view()->title(__('Books'));
    }
};
?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-serif text-3xl font-semibold">{{ __('Books') }}</h1>
            <p class="mt-1 text-ink-soft">{{ trans_choice(':count book|:count books', $this->copies->total()) }}</p>
        </div>
        @can('addBooks', $this->library)
            <a href="{{ route('books.create') }}" wire:navigate class="btn-primary w-auto">+ {{ __('Add a book') }}</a>
        @endcan
    </div>

    @if ($this->borrowedBooks->isNotEmpty())
        <ul class="mt-4 space-y-1 rounded-lg bg-accent/5 px-3 py-2 text-sm">
            @foreach ($this->borrowedBooks as $borrowedLoan)
                <li wire:key="borrowed-{{ $borrowedLoan->id }}">
                    <span class="font-medium">{{ $borrowedLoan->copy->edition->title }}</span>
                    <span class="text-ink-soft">· {{ __('Borrowed from :library', ['library' => $borrowedLoan->copy->library->name]) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-6 space-y-3">
        @if ($this->selectedPerson)
            <p class="inline-flex items-center gap-2 rounded-full bg-accent/10 py-1 pr-1 pl-3 text-sm text-accent-dark">
                {{ __('Books with :name', ['name' => $this->selectedPerson->name]) }}
                <button type="button" wire:click="$set('person', '')" class="rounded-full px-2 hover:bg-accent/20" aria-label="{{ __('Remove filter') }}">✕</button>
            </p>
        @endif

        @if ($hidden)
            <p class="inline-flex items-center gap-2 rounded-full bg-accent/10 py-1 pr-1 pl-3 text-sm text-accent-dark">
                {{ __('Hidden from your gift page.') }}
                <button type="button" wire:click="$set('hidden', false)" class="rounded-full px-2 hover:bg-accent/20" aria-label="{{ __('Remove filter') }}">✕</button>
            </p>
        @endif

        <label for="search" class="sr-only">{{ __('Search') }}</label>
        <input wire:model.live.debounce.300ms="search" id="search" type="search" autocomplete="off"
               placeholder="{{ __('Search by title, author or ISBN') }}" class="form-input">

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
                <label for="genre" class="sr-only">{{ __('Genre') }}</label>
                <select wire:model.live="genre" id="genre" class="form-input text-sm">
                    <option value="">{{ __('All genres') }}</option>
                    @foreach ($this->genres as $value => $label)
                        <option wire:key="genre-{{ $value }}" value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tag" class="sr-only">{{ __('Tag') }}</label>
                <select wire:model.live="tag" id="tag" class="form-input text-sm">
                    <option value="">{{ __('All tags') }}</option>
                    @foreach ($this->tags as $tagOption)
                        <option wire:key="tag-{{ $tagOption->id }}" value="{{ $tagOption->id }}">{{ $tagOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="language" class="sr-only">{{ __('Language') }}</label>
                <select wire:model.live="language" id="language" class="form-input text-sm">
                    <option value="">{{ __('All languages') }}</option>
                    @foreach ($this->languages as $code => $name)
                        <option wire:key="language-{{ $code }}" value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                    <option value="none">{{ __('Not specified') }}</option>
                </select>
            </div>
            <div>
                <label for="sort" class="sr-only">{{ __('Sort') }}</label>
                <select wire:model.live="sort" id="sort" class="form-input text-sm">
                    <option value="newest">{{ __('Newest first') }}</option>
                    <option value="title">{{ __('By title') }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="mt-6" wire:loading.class="opacity-60" wire:target="search, person, genre, tag, language, hidden, sort">
        @if ($this->copies->isEmpty())
            <div class="rounded-xl border border-dashed border-ink/20 px-6 py-12 text-center">
                @if ($this->hasFilters)
                    <p class="text-ink-soft">{{ __('No books match your search.') }}</p>
                    <button type="button" wire:click="clearFilters" class="link mt-2 text-sm">{{ __('Clear filters') }}</button>
                @else
                    <p class="text-ink-soft">{{ __('No books yet. Grab the nearest shelf and add the first one!') }}</p>
                @endif
            </div>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->copies as $copy)
                    <li wire:key="copy-{{ $copy->id }}">
                        <x-book-card :copy="$copy" />
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">
                {{ $this->copies->links() }}
            </div>
        @endif
    </div>
</div>
