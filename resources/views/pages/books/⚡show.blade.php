<?php

use App\Enums\BookCondition;
use App\Enums\ContributorRole;
use App\Enums\CopyStatus;
use App\Models\Copy;
use App\Models\CopyTransfer;
use App\Models\Edition;
use App\Models\Loan;
use App\Services\CoverImage;
use App\Support\Concerns\SuggestsContributorNames;
use App\Support\Concerns\SuggestsPublisherNames;
use App\Support\Language;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use SuggestsContributorNames, SuggestsPublisherNames, WithFileUploads;

    public Copy $copy;

    public ?TemporaryUploadedFile $coverPhoto = null;

    /** @var list<string> */
    public array $tagIds = [];

    public string $newTag = '';

    public string $notes = '';

    public string $condition = '';

    public string $status = '';

    public string $borrowerName = '';

    public string $borrowerPhone = '';

    public string $dueOn = '';

    public bool $editingEdition = false;

    public string $title = '';

    public string $subtitle = '';

    /** Comma-separated names per contributor role (property = role in plural). */
    public string $authors = '';

    public string $translators = '';

    public string $compilers = '';

    public string $editors = '';

    public string $illustrators = '';

    public string $language = '';

    public string $publisher = '';

    public string $publishedYear = '';

    public string $pageCount = '';

    public string $linkSearch = '';

    /** @var list<string> */
    public array $genres = [];

    public function mount(Copy $copy): void
    {
        $this->authorize('view', $copy);

        $this->genres = $copy->edition->genres->pluck('slug')->all();
        $this->tagIds = $copy->tags()->pluck('tags.id')->map(fn (int $id): string => (string) $id)->all();
        $this->notes = (string) $copy->notes;
        $this->condition = (string) $copy->condition?->value;
        $this->status = $copy->status->value;
    }

    #[Computed]
    public function edition(): Edition
    {
        return $this->copy->edition()->with(['authors', 'contributors', 'work', 'genres', 'publisher'])->firstOrFail();
    }

    #[Computed]
    public function canEditGenres(): bool
    {
        return auth()->user()->can('updateGenres', $this->copy);
    }

    public function saveGenres(): void
    {
        $this->authorize('updateGenres', $this->copy);

        $this->validate([
            'genres' => ['array', 'max:'.Edition::MAX_GENRES],
            'genres.*' => ['string', Rule::exists('genres', 'slug')],
        ]);

        $this->edition->syncGenres($this->genres);
        unset($this->edition, $this->canEditGenres);

        session()->flash('status', __('Changes saved.'));
    }

    /**
     * Viewers only look: no copy, tag or link editing.
     */
    #[Computed]
    public function canEditCopy(): bool
    {
        return auth()->user()->can('update', $this->copy);
    }

    #[Computed]
    public function canEditEdition(): bool
    {
        return auth()->user()->can('updateEdition', $this->copy);
    }

    #[Computed]
    public function tags(): Collection
    {
        return $this->copy->library->tags()->orderBy('name')->get();
    }

    /**
     * Other copies of the very same edition in this library.
     *
     * @return Collection<int, Copy>
     */
    #[Computed]
    public function otherCopies(): Collection
    {
        return $this->copy->library->copies()
            ->where('edition_id', $this->copy->edition_id)
            ->whereKeyNot($this->copy->id)
            ->get();
    }

    /**
     * Copies in this library of other editions of the same work.
     *
     * @return Collection<int, Copy>
     */
    #[Computed]
    public function sameWorkCopies(): Collection
    {
        if ($this->edition->work_id === null) {
            return new Collection;
        }

        return $this->copy->library->copies()
            ->whereHas('edition', fn ($query) => $query
                ->where('work_id', $this->edition->work_id)
                ->whereKeyNot($this->edition->id))
            ->with('edition.authors')
            ->get();
    }

    /**
     * Books in this library that could be another edition of this one.
     *
     * @return Collection<int, Copy>
     */
    #[Computed]
    public function linkCandidates(): Collection
    {
        if (mb_strlen(trim($this->linkSearch)) < 2) {
            return new Collection;
        }

        return $this->copy->library->copies()
            ->matching($this->linkSearch)
            ->where('edition_id', '!=', $this->edition->id)
            ->when($this->edition->work_id, fn ($query, int $workId) => $query->whereHas(
                'edition', fn ($edition) => $edition->whereNull('work_id')->orWhere('work_id', '!=', $workId),
            ))
            ->with('edition.authors')
            ->limit(6)
            ->get()
            ->unique('edition_id')
            ->values();
    }

    public function saveCopy(): void
    {
        $this->authorize('update', $this->copy);

        $libraryId = $this->copy->library_id;

        $this->validate([
            'tagIds' => ['array'],
            'tagIds.*' => ['integer', Rule::exists('tags', 'id')->where('library_id', $libraryId)],
            'newTag' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'condition' => ['nullable', Rule::enum(BookCondition::class)],
            'status' => ['required', Rule::enum(CopyStatus::class)],
        ]);

        DB::transaction(function (): void {
            $library = $this->copy->library;

            $tagIds = array_map('intval', $this->tagIds);

            if (trim($this->newTag) !== '') {
                $tagIds[] = $library->tags()->firstOrCreate(['name' => trim($this->newTag)])->id;
            }

            $this->copy->update([
                'notes' => trim($this->notes) ?: null,
                'condition' => $this->condition ?: null,
                'status' => $this->status,
            ]);

            $this->copy->tags()->sync(array_unique($tagIds));

            if ($this->copy->isLost()) {
                $this->copy->pendingTransfer()?->cancel();
                $this->copy->closeListings();
                $this->copy->pendingLoanLink()?->cancelLink();
            }
        });

        $this->tagIds = $this->copy->tags()->pluck('tags.id')->map(fn (int $id): string => (string) $id)->all();
        $this->reset('newTag');
        unset($this->tags, $this->pendingTransfer, $this->canTransfer);

        session()->flash('status', __('Changes saved.'));
    }

    #[Computed]
    public function canTransfer(): bool
    {
        return auth()->user()->can('transfer', $this->copy);
    }

    #[Computed]
    public function canLend(): bool
    {
        return auth()->user()->can('lend', $this->copy);
    }

    #[Computed]
    public function activeLoan(): ?Loan
    {
        return $this->copy->activeLoan();
    }

    #[Computed]
    public function pendingLoanLink(): ?Loan
    {
        return $this->copy->pendingLoanLink();
    }

    /**
     * @return Collection<int, Loan>
     */
    #[Computed]
    public function pastLoans(): Collection
    {
        return $this->copy->loans()->whereNotNull('returned_at')->latest('returned_at')->limit(10)->get();
    }

    public function lendToPerson(): void
    {
        $this->authorize('lend', $this->copy);

        $validated = $this->validate([
            'borrowerName' => ['required', 'string', 'max:255'],
            'borrowerPhone' => ['nullable', 'string', 'max:40'],
            'dueOn' => ['nullable', 'date', 'after_or_equal:'.now(Loan::TIMEZONE)->toDateString()],
        ]);

        $this->copy->pendingLoanLink()?->cancelLink();
        $this->copy->lendToPerson(
            auth()->user(),
            trim($validated['borrowerName']),
            trim((string) $validated['borrowerPhone']) ?: null,
            $validated['dueOn'] ?: null,
        );

        $this->reset('borrowerName', 'borrowerPhone', 'dueOn');
        $this->refreshLoans();

        session()->flash('status', __('Changes saved.'));
    }

    public function createLoanLink(): void
    {
        $this->authorize('lend', $this->copy);

        $validated = $this->validate([
            'dueOn' => ['nullable', 'date', 'after_or_equal:'.now(Loan::TIMEZONE)->toDateString()],
        ]);

        $this->copy->offerLoan(auth()->user(), $validated['dueOn'] ?: null);

        $this->reset('dueOn');
        $this->refreshLoans();
    }

    public function cancelLoanLink(): void
    {
        $this->authorize('update', $this->copy);

        $this->copy->pendingLoanLink()?->cancelLink();

        $this->refreshLoans();
    }

    public function markLoanReturned(): void
    {
        $this->authorize('update', $this->copy);

        $this->copy->activeLoan()?->markReturned();

        $this->refreshLoans();

        session()->flash('status', __('Marked as returned.'));
    }

    public function deleteLoanRecord(int $loanId): void
    {
        $this->authorize('update', $this->copy);

        $this->copy->loans()->whereNotNull('returned_at')->findOrFail($loanId)->delete();

        unset($this->pastLoans);
    }

    private function refreshLoans(): void
    {
        unset($this->activeLoan, $this->pendingLoanLink, $this->pastLoans, $this->canLend, $this->canTransfer);
    }

    #[Computed]
    public function pendingTransfer(): ?CopyTransfer
    {
        return $this->copy->pendingTransfer();
    }

    /**
     * @return Collection<int, CopyTransfer>
     */
    #[Computed]
    public function transferHistory(): Collection
    {
        return $this->copy->transferHistory()->get();
    }

    public function offerTransfer(): void
    {
        $this->authorize('transfer', $this->copy);

        $this->copy->offerTransfer(auth()->user());

        unset($this->pendingTransfer);
    }

    public function cancelTransfer(): void
    {
        $this->authorize('update', $this->copy);

        $this->pendingTransfer?->cancel();

        unset($this->pendingTransfer);

        session()->flash('status', __('Giving cancelled.'));
    }

    #[Computed]
    public function isHiddenFromGiftPage(): bool
    {
        return $this->copy->isHiddenFromGiftPage();
    }

    public function toggleGiftPageVisibility(): void
    {
        $this->authorize('update', $this->copy);

        $this->copy->setHiddenFromGiftPage(! $this->isHiddenFromGiftPage);

        unset($this->isHiddenFromGiftPage);
    }

    #[Computed]
    public function canChangeCover(): bool
    {
        return auth()->user()->can('updateCover', $this->copy);
    }

    /**
     * Saved as soon as a photo is chosen. The size limits matter for photos the
     * browser could not shrink first: GD needs about 5 bytes of memory per pixel.
     */
    public function updatedCoverPhoto(): void
    {
        $this->authorize('updateCover', $this->copy);

        $this->validate([
            'coverPhoto' => ['required', 'mimes:jpeg,png,webp', 'max:10240', Rule::dimensions()->minWidth(100)->minHeight(100)->maxWidth(4200)->maxHeight(4200)],
        ]);

        $path = app(CoverImage::class)->store($this->coverPhoto);

        if ($path === null) {
            $this->addError('coverPhoto', __('This image could not be read. Try another photo.'));

            return;
        }

        $this->edition->setUploadedCover($path);
        $this->refreshCover();

        session()->flash('status', __('Cover saved.'));
    }

    public function removeCover(): void
    {
        $this->authorize('updateCover', $this->copy);

        $this->edition->setUploadedCover(null);
        $this->refreshCover();

        session()->flash('status', __('Cover photo removed.'));
    }

    private function refreshCover(): void
    {
        $this->reset('coverPhoto');
        $this->copy->unsetRelation('edition');
        unset($this->edition, $this->canChangeCover);
    }

    public function editEdition(): void
    {
        $this->authorize('updateEdition', $this->copy);

        $edition = $this->edition;

        $this->title = $edition->title;
        $this->subtitle = (string) $edition->subtitle;
        foreach (ContributorRole::cases() as $role) {
            $this->{$role->value.'s'} = $edition->contributors
                ->filter(fn ($person): bool => $person->pivot->role === $role->value)
                ->pluck('name')
                ->join(', ');
        }
        $this->language = (string) $edition->language;
        $this->publisher = (string) $edition->publisher?->name;
        $this->publishedYear = (string) ($edition->published_year ?? '');
        $this->pageCount = (string) ($edition->page_count ?? '');
        $this->editingEdition = true;
    }

    public function cancelEditEdition(): void
    {
        $this->editingEdition = false;
        $this->resetErrorBag();
    }

    public function saveEdition(): void
    {
        $this->authorize('updateEdition', $this->copy);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:500'],
            'translators' => ['nullable', 'string', 'max:500'],
            'compilers' => ['nullable', 'string', 'max:500'],
            'editors' => ['nullable', 'string', 'max:500'],
            'illustrators' => ['nullable', 'string', 'max:500'],
            'language' => ['nullable', Rule::in(Language::CODES)],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publishedYear' => ['nullable', 'integer', 'between:1450,'.(now()->year + 1)],
            'pageCount' => ['nullable', 'integer', 'between:1,20000'],
        ]);

        DB::transaction(function (): void {
            $edition = $this->edition;

            $edition->fill([
                'title' => trim($this->title),
                'subtitle' => trim($this->subtitle) ?: null,
                'language' => $this->language ?: null,
                'published_year' => $this->publishedYear !== '' ? (int) $this->publishedYear : null,
                'page_count' => $this->pageCount !== '' ? (int) $this->pageCount : null,
            ]);
            $edition->setPublisherName($this->publisher);
            $edition->save();

            foreach (ContributorRole::cases() as $role) {
                $edition->syncContributorNames($role, $this->{$role->value.'s'});
            }
        });

        unset($this->edition);
        $this->editingEdition = false;

        session()->flash('status', __('Book details saved.'));
    }

    public function linkTo(int $copyId): void
    {
        $this->authorize('update', $this->copy);

        $other = $this->copy->library->copies()->findOrFail($copyId);

        $this->edition->linkToSameWorkAs($other->edition);

        unset($this->edition, $this->sameWorkCopies, $this->linkCandidates);
        $this->reset('linkSearch');

        session()->flash('status', __('Linked as the same book in another edition.'));
    }

    public function unlink(): void
    {
        $this->authorize('update', $this->copy);

        $this->edition->unlinkFromWork();

        unset($this->edition, $this->sameWorkCopies);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->copy);

        $title = $this->edition->title;
        $this->copy->delete();

        session()->flash('status', __('“:title” removed from your library.', ['title' => $title]));

        $this->redirectRoute('books.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title($this->edition->title);
    }
};
?>

<div class="max-w-3xl">
    <a href="{{ route('books.index') }}" wire:navigate class="link text-sm">← {{ __('Books') }}</a>

    <x-flash class="mt-4" />

    @if ($editingEdition)
        <form wire:submit="saveEdition" class="mt-4 space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h1 class="font-serif text-2xl font-semibold">{{ __('Correct book details') }}</h1>

            <div>
                <label for="title" class="form-label">{{ __('Title') }}</label>
                <input wire:model="title" id="title" type="text" required maxlength="255" class="form-input">
                @error('title') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="subtitle" class="form-label">{{ __('Subtitle') }}</label>
                <input wire:model="subtitle" id="subtitle" type="text" maxlength="255" class="form-input">
                @error('subtitle') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <x-contributor-fields />
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="language" class="form-label">{{ __('Language of this edition') }}</label>
                    <x-language-picker wire:model="language" />
                    @error('language') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="publisher" class="form-label">{{ __('Publisher') }}</label>
                    <x-publisher-input id="publisher" wire:model="publisher" />
                    @error('publisher') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="publishedYear" class="form-label">{{ __('Year') }}</label>
                        <input wire:model="publishedYear" id="publishedYear" type="number" inputmode="numeric" class="form-input">
                        @error('publishedYear') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="pageCount" class="form-label">{{ __('Pages') }}</label>
                        <input wire:model="pageCount" id="pageCount" type="number" inputmode="numeric" class="form-input">
                        @error('pageCount') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Save') }}</button>
                <button type="button" wire:click="cancelEditEdition" class="text-sm text-ink-soft hover:text-ink">{{ __('Cancel') }}</button>
            </div>
        </form>
    @else
        <div x-data="coverUpload" class="mt-4">
        <section class="flex gap-5">
            <div class="w-24 shrink-0 self-start sm:w-32">
                <x-book-cover :edition="$this->edition" class="w-full" />
                @if ($this->canChangeCover)
                    <label class="link mt-2 block cursor-pointer text-xs">
                        <span x-show="!uploading">{{ $this->edition->hasCover() ? __('Change cover') : __('Add cover photo') }}</span>
                        <span x-show="uploading" x-cloak>{{ __('Uploading…') }}</span>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="choose" x-bind:disabled="uploading">
                    </label>
                    @if ($this->edition->cover_path)
                        <button type="button" wire:click="removeCover" wire:confirm="{{ __('Remove this cover photo?') }}" class="mt-1 text-xs text-ink-soft underline hover:text-ink">{{ __('Remove photo') }}</button>
                    @endif
                @endif
            </div>
            <div class="min-w-0">
                <h1 class="font-serif text-2xl font-semibold leading-tight sm:text-3xl">{{ $this->edition->title }}</h1>
                @if ($this->edition->subtitle)
                    <p class="mt-1 text-ink-soft">{{ $this->edition->subtitle }}</p>
                @endif
                @if ($this->edition->authors->isNotEmpty())
                    <p class="mt-2"><x-people-links :people="$this->edition->authors" /></p>
                @endif

                <x-genre-chips :genres="$this->edition->genres" class="mt-2" />

                <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    @foreach ($this->edition->otherContributorsByRole() as $group)
                        <dt class="text-ink-soft">{{ $group['role']->label() }}</dt><dd><x-people-links :people="$group['people']" /></dd>
                    @endforeach
                    @if ($this->edition->publisher || $this->edition->published_year)
                        <dt class="text-ink-soft">{{ __('Publisher') }}</dt>
                        <dd>{{ collect([$this->edition->publisher?->name, $this->edition->published_year])->filter()->join(', ') }}</dd>
                    @endif
                    @if ($this->edition->page_count)
                        <dt class="text-ink-soft">{{ __('Pages') }}</dt><dd>{{ $this->edition->page_count }}</dd>
                    @endif
                    @if ($this->edition->language)
                        <dt class="text-ink-soft">{{ __('Language') }}</dt><dd>{{ \App\Support\Language::name($this->edition->language) }}</dd>
                    @endif
                    @if ($this->edition->isbn_13 || $this->edition->isbn_10)
                        <dt class="text-ink-soft">ISBN</dt><dd>{{ $this->edition->isbn_13 ?? $this->edition->isbn_10 }}</dd>
                    @endif
                </dl>

                @if ($this->canEditEdition)
                    <button type="button" wire:click="editEdition" class="link mt-3 text-sm">{{ __('Correct book details') }}</button>
                @else
                    <p class="mt-3 text-xs text-ink-soft">{{ __('Other libraries also have this edition, so its details cannot be changed here.') }}</p>
                @endif
            </div>
        </section>
        @error('coverPhoto') <p class="form-error">{{ $message }}</p> @enderror
        <p x-show="failed" x-cloak class="form-error">{{ __('The photo could not be uploaded. Try again.') }}</p>
        </div>
    @endif

    @if ($this->otherCopies->isNotEmpty())
        <p class="mt-6 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200">
            {{ trans_choice('You have :count more copy of this edition:|You have :count more copies of this edition:', $this->otherCopies->count()) }}
            @foreach ($this->otherCopies as $otherCopy)
                <a wire:key="other-{{ $otherCopy->id }}" href="{{ route('books.show', $otherCopy) }}" wire:navigate class="font-medium underline">{{ __('Copy :number', ['number' => $loop->iteration]) }}</a>@if (! $loop->last), @endif
            @endforeach
        </p>
    @endif

    @if ($this->canEditGenres)
        <form wire:submit="saveGenres" class="mt-8 space-y-3 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('Genre') }}</h2>
            <x-genre-picker model="genres" :selected="$this->edition->genres->pluck('slug')->all()" />
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled" wire:target="saveGenres">{{ __('Save') }}</button>
        </form>
    @endif

    @if ($this->activeLoan && ! $this->canEditCopy)
        <p class="mt-8 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('Lent to :name', ['name' => $this->activeLoan->borrower_name]) }}</p>
    @endif

    <section class="mt-8 rounded-xl border border-ink/10 bg-white p-4 sm:p-6" x-data="{ copied: false }">
        <h2 class="font-serif text-xl font-semibold">{{ __('Book ID') }}</h2>
        <p class="mt-1 text-xs text-ink-soft">{{ __('Each physical book has its own public page. If it gets lost, whoever finds it can write to you from there, without seeing your contact details.') }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
            <input type="text" readonly value="{{ $this->copy->foundPageUrl() }}" aria-label="{{ __('Book ID') }}" class="form-input min-w-0 basis-full text-sm sm:flex-1 sm:basis-0" x-on:focus="$el.select()">
            <button type="button" class="link shrink-0 text-sm"
                    x-on:click="navigator.clipboard.writeText(@js($this->copy->foundPageUrl())); copied = true; setTimeout(() => copied = false, 2000)">
                <span x-show="! copied">{{ __('Copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
            </button>
            <a href="{{ $this->copy->foundPageUrl() }}" target="_blank" rel="noopener" class="link shrink-0 text-sm">{{ __('Open') }}</a>
            @if ($this->canEditCopy && $this->copy->library_id === auth()->user()->currentLibrary()?->id)
                <a href="{{ route('library.labels', ['book' => $this->copy->id]) }}" wire:navigate class="link shrink-0 text-sm">{{ __('Print a label') }}</a>
            @endif
        </div>
        <p class="mt-2 text-sm">{{ __('On the label:') }} <span class="font-mono font-medium">{{ $this->copy->shortBookId() }}</span></p>
    </section>

    @if ($this->transferHistory->isNotEmpty())
        <section class="mt-8 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('History') }}</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($this->transferHistory as $transfer)
                    <li wire:key="history-{{ $transfer->id }}">{{ __('From :library, :date', ['library' => $transfer->from_library_name, 'date' => $transfer->accepted_at->toDateString()]) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (! $this->canEditCopy)
        <section class="mt-8 space-y-3 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('About this copy') }}</h2>
            <p class="text-sm">
                {{ __('Status') }}: {{ $this->copy->status->label() }}
                @if ($this->copy->condition)
                    · {{ __('Condition') }}: {{ $this->copy->condition->label() }}
                @endif
            </p>
            @if ($this->copy->tags->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->copy->tags as $tag)
                        <span class="rounded-full border border-ink/15 bg-paper px-3 py-1 text-sm">{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif
            @if (filled($this->copy->notes))
                <p class="whitespace-pre-line text-sm">{{ $this->copy->notes }}</p>
            @endif
            @if ($this->copy->tags->isEmpty() && blank($this->copy->notes))
                <p class="text-sm text-ink-soft">{{ __('No tags or notes yet.') }}</p>
            @endif
            @foreach ($this->sameWorkCopies as $sibling)
                <p wire:key="sibling-{{ $sibling->id }}" class="text-sm">
                    {{ __('Other edition:') }} <a href="{{ route('books.show', $sibling) }}" wire:navigate class="link">{{ $sibling->edition->title }}</a>
                </p>
            @endforeach
        </section>
    @else
    <form wire:submit="saveCopy" class="mt-8 space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <h2 class="font-serif text-xl font-semibold">{{ __('About your copy') }}</h2>

        <div>
            <span class="form-label">{{ __('Tags') }}</span>
            @if ($this->tags->isNotEmpty())
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach ($this->tags as $tag)
                        <label wire:key="tag-{{ $tag->id }}" class="inline-flex items-center gap-1.5 rounded-full border border-ink/15 bg-paper px-3 py-1 text-sm">
                            <input wire:model="tagIds" type="checkbox" value="{{ $tag->id }}" class="rounded border-ink/30 text-accent">
                            {{ $tag->name }}
                        </label>
                    @endforeach
                </div>
            @endif
            <label for="newTag" class="sr-only">{{ __('New tag') }}</label>
            <input wire:model="newTag" id="newTag" type="text" maxlength="100" placeholder="{{ __('New tag, e.g. Gift, To read') }}" class="form-input">
            @error('tagIds.*') <p class="form-error">{{ $message }}</p> @enderror
            @error('newTag') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="notes" class="form-label">{{ __('Notes') }}</label>
            <textarea wire:model="notes" id="notes" rows="2" maxlength="2000" placeholder="{{ __('e.g. Gift from grandmother, signed by the author') }}" class="form-input"></textarea>
            @error('notes') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="condition" class="form-label">{{ __('Condition') }}</label>
                <select wire:model="condition" id="condition" class="form-input">
                    <option value="">{{ __('Not set') }}</option>
                    @foreach (BookCondition::cases() as $bookCondition)
                        <option wire:key="condition-{{ $bookCondition->value }}" value="{{ $bookCondition->value }}">{{ $bookCondition->label() }}</option>
                    @endforeach
                </select>
                @error('condition') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="form-label">{{ __('Status') }}</label>
                <select wire:model="status" id="status" class="form-input">
                    @foreach (CopyStatus::cases() as $copyStatus)
                        <option wire:key="status-{{ $copyStatus->value }}" value="{{ $copyStatus->value }}">{{ $copyStatus->label() }}</option>
                    @endforeach
                </select>
                @error('status') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled" wire:target="saveCopy">{{ __('Save') }}</button>
    </form>

    <section class="mt-8 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div>
                <h2 class="font-serif text-xl font-semibold">{{ __('Gift page') }}</h2>
                <p class="mt-1 text-sm">{{ $this->isHiddenFromGiftPage ? __('Hidden from your gift page.') : __('Shown on your gift page.') }}</p>
            </div>
            <button type="button" wire:click="toggleGiftPageVisibility" wire:loading.attr="disabled" wire:target="toggleGiftPageVisibility"
                    class="min-h-11 shrink-0 cursor-pointer rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white">
                {{ $this->isHiddenFromGiftPage ? __('Show this book') : __('Hide this book') }}
            </button>
        </div>
        <p class="mt-2 text-xs text-ink-soft">{{ __('When a book is hidden, friends don’t see it and the check says you don’t have it, so someone could still give it to you.') }}</p>
    </section>

    <section class="mt-8 space-y-3 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <h2 class="font-serif text-xl font-semibold">{{ __('Other editions of this book') }}</h2>
        <p class="text-sm text-ink-soft">{{ __('Link the original and its translations, so you can see when you own the same book twice in different editions.') }}</p>

        @if ($this->sameWorkCopies->isNotEmpty())
            <ul class="divide-y divide-ink/10">
                @foreach ($this->sameWorkCopies as $sibling)
                    <li wire:key="sibling-{{ $sibling->id }}" class="py-2">
                        <a href="{{ route('books.show', $sibling) }}" wire:navigate class="link">{{ $sibling->edition->title }}</a>
                        <span class="text-sm text-ink-soft">{{ collect([\App\Support\Language::name($sibling->edition->language)])->filter()->join(' · ') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($this->edition->work_id)
            <button type="button" wire:click="unlink" class="text-sm text-ink-soft underline hover:text-ink">{{ __('Unlink this edition') }}</button>
        @endif

        <div>
            <label for="linkSearch" class="form-label">{{ __('Find another edition in your library') }}</label>
            <input wire:model.live.debounce.300ms="linkSearch" id="linkSearch" type="search" autocomplete="off"
                   placeholder="{{ __('Search by title, author or ISBN') }}" class="form-input">
        </div>

        @if ($this->linkCandidates->isNotEmpty())
            <ul class="divide-y divide-ink/10 rounded-lg border border-ink/10">
                @foreach ($this->linkCandidates as $candidate)
                    <li wire:key="candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-3 px-3 py-2">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $candidate->edition->title }}</span>
                            <span class="block truncate text-xs text-ink-soft">{{ collect([$candidate->edition->authors->pluck('name')->join(', '), \App\Support\Language::name($candidate->edition->language)])->filter()->join(' · ') }}</span>
                        </span>
                        <button type="button" wire:click="linkTo({{ $candidate->id }})" class="shrink-0 rounded-lg border border-accent px-3 py-1 text-sm font-medium text-accent hover:bg-accent hover:text-white">{{ __('Link') }}</button>
                    </li>
                @endforeach
            </ul>
        @elseif (mb_strlen(trim($linkSearch)) >= 2)
            <p class="text-sm text-ink-soft">{{ __('No books match your search.') }}</p>
        @endif
    </section>

    <section id="lending" class="mt-8 scroll-mt-6 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <h2 class="font-serif text-xl font-semibold">{{ __('Lending') }}</h2>

        @if ($this->activeLoan)
            <p class="mt-2 text-sm font-medium">{{ __('Lent to :name', ['name' => $this->activeLoan->borrower_name]) }}</p>
            @can('viewBorrowerPhone', $this->copy->library)
                @if ($this->activeLoan->borrower_phone)
                    <p class="text-sm text-ink-soft">{{ $this->activeLoan->borrower_phone }}</p>
                @endif
            @endcan
            <x-loan-due :loan="$this->activeLoan" class="mt-1" />
            <button type="button" wire:click="markLoanReturned" wire:loading.attr="disabled"
                    class="mt-3 min-h-11 cursor-pointer rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white">
                {{ __('It came back') }}
            </button>
        @elseif ($this->canLend)
            @if ($this->pendingLoanLink)
                @php($loanUrl = route('loans.show', $this->pendingLoanLink->token))
                <div class="mt-3" x-data="{ copied: false }">
                    <p class="text-sm">{{ __('Send this link to the other library. It works once and expires on :date.', ['date' => $this->pendingLoanLink->expires_at->toDateString()]) }}</p>
                    <div class="mt-2 flex gap-2">
                        <input type="text" readonly value="{{ $loanUrl }}" aria-label="{{ __('Lending') }}" class="form-input text-sm" x-on:focus="$el.select()">
                        <button type="button" class="shrink-0 text-sm link"
                                x-on:click="navigator.clipboard.writeText(@js($loanUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="! copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                        </button>
                        <button type="button" wire:click="cancelLoanLink" class="shrink-0 text-sm text-red-700 hover:underline">{{ __('Cancel') }}</button>
                    </div>
                </div>
            @endif

            <form wire:submit="lendToPerson" class="mt-3 space-y-3">
                <div>
                    <label for="dueOn" class="form-label">{{ __('Return by (optional)') }}</label>
                    <input wire:model="dueOn" id="dueOn" type="date" class="form-input">
                    @error('dueOn') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="borrowerName" class="form-label">{{ __('Who is borrowing it?') }}</label>
                        <input wire:model="borrowerName" id="borrowerName" type="text" maxlength="255" class="form-input">
                        @error('borrowerName') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="borrowerPhone" class="form-label">{{ __('Their phone (optional)') }}</label>
                        <input wire:model="borrowerPhone" id="borrowerPhone" type="tel" maxlength="40" class="form-input">
                        @error('borrowerPhone') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-xs text-ink-soft">{{ __('The phone number is shown only to the people who run this library and is erased when the book comes back.') }}</p>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled" wire:target="lendToPerson">{{ __('Lend to this person') }}</button>
                    @unless ($this->pendingLoanLink)
                        <button type="button" wire:click="createLoanLink" class="link text-sm">{{ __('Or lend to another library') }}</button>
                    @endunless
                </div>
            </form>
        @elseif ($this->copy->isLost())
            <p class="mt-2 text-sm text-ink-soft">{{ __('Lost books cannot be lent.') }}</p>
        @else
            <p class="mt-2 text-sm text-ink-soft">{{ __('This book has an open giving link. Cancel it first to lend the book.') }}</p>
        @endif

        @if ($this->pastLoans->isNotEmpty())
            <h3 class="mt-4 font-medium">{{ __('Earlier loans') }}</h3>
            <ul class="mt-1 divide-y divide-ink/10 text-sm">
                @foreach ($this->pastLoans as $pastLoan)
                    <li wire:key="past-loan-{{ $pastLoan->id }}" class="flex items-center justify-between gap-3 py-1.5">
                        <span class="min-w-0 truncate">{{ $pastLoan->borrower_name }} · {{ $pastLoan->returned_at->timezone(Loan::TIMEZONE)->toDateString() }}</span>
                        <button type="button" wire:click="deleteLoanRecord({{ $pastLoan->id }})" class="shrink-0 text-xs text-red-700 hover:underline">{{ __('Delete') }}</button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if (config('market.enabled'))
        @php($activeListing = $this->copy->activeListing())
        <section class="mt-8 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('Sell or swap') }}</h2>
            @if ($this->activeLoan)
                <p class="mt-2 text-sm text-ink-soft">{{ __('This book is on loan. It can be offered when it comes back.') }}</p>
            @elseif ($activeListing)
                <p class="mt-2 text-sm">{{ __('This book is on the market.') }} <a href="{{ route('market.show', $activeListing) }}" wire:navigate class="link">{{ __('Open the listing') }}</a></p>
            @elseif ($this->copy->isLost())
                <p class="mt-2 text-sm text-ink-soft">{{ __('Lost books cannot be offered.') }}</p>
            @elseif (auth()->user()->can('createListing', $this->copy))
                <p class="mt-2 text-xs text-ink-soft">{{ __('Only this book is shown to other members. Your library and your other books stay private.') }}</p>
                <a href="{{ route('market.create', $this->copy) }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white">{{ __('Offer this book') }}</a>
            @else
                <p class="mt-2 text-sm text-ink-soft">{{ __('Your account cannot use the market right now.') }}</p>
            @endif
        </section>
    @endif

    <section class="mt-8 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <h2 class="font-serif text-xl font-semibold">{{ __('Give to another library') }}</h2>
        <p class="mt-2 text-xs text-ink-soft">{{ __('Your notes, tags and gift page setting stay behind. The new owner sees the book, its condition and the names of the libraries it came from.') }}</p>

        @if ($this->pendingTransfer)
            @php($transferUrl = route('transfers.show', $this->pendingTransfer->token))
            <div class="mt-3" x-data="{ copied: false }">
                <p class="text-sm">{{ __('Send this link to the new owner. It works once and expires on :date. The book stays here until they accept.', ['date' => $this->pendingTransfer->expires_at->toDateString()]) }}</p>
                <div class="mt-2 flex gap-2">
                    <input type="text" readonly value="{{ $transferUrl }}" aria-label="{{ __('Give to another library') }}" class="form-input text-sm" x-on:focus="$el.select()">
                    <button type="button" class="shrink-0 text-sm link"
                            x-on:click="navigator.clipboard.writeText(@js($transferUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="! copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                    </button>
                    <button type="button" wire:click="cancelTransfer" class="shrink-0 text-sm text-red-700 hover:underline">{{ __('Cancel') }}</button>
                </div>
            </div>
        @elseif ($this->canTransfer)
            <button type="button" wire:click="offerTransfer" wire:loading.attr="disabled" wire:target="offerTransfer"
                    class="mt-3 min-h-11 cursor-pointer rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white">
                {{ __('Create a link') }}
            </button>
        @elseif ($this->activeLoan)
            <p class="mt-3 text-sm text-ink-soft">{{ __('This book is on loan. It can be given away when it comes back.') }}</p>
        @elseif ($this->pendingLoanLink)
            <p class="mt-3 text-sm text-ink-soft">{{ __('This book has an open lending link. Cancel it first to give the book away.') }}</p>
        @else
            <p class="mt-3 text-sm text-ink-soft">{{ __('Lost books cannot be given away.') }}</p>
        @endif
    </section>

    <div class="mt-8 border-t border-ink/10 pt-6">
        <button type="button" wire:click="delete"
                wire:confirm="{{ __('Remove this book from your library?') }}"
                class="text-sm font-medium text-red-700 hover:underline">{{ __('Remove from library') }}</button>
        <p class="mt-1 text-xs text-ink-soft">{{ __('Use this when the book was added by mistake or is no longer yours.') }}</p>
    </div>
    @endif
</div>
