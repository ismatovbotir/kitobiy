<?php

use App\Enums\ContributorRole;
use App\Enums\EditionSource;
use App\Models\Edition;
use App\Models\Publisher;
use App\Models\PublisherMember;
use App\Services\CoverImage;
use App\Support\Concerns\SuggestsContributorNames;
use App\Support\Isbn;
use App\Support\Language;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use SuggestsContributorNames, WithFileUploads;

    #[Locked]
    public Publisher $publisher;

    /** The book being edited; null when adding a new one. */
    #[Locked]
    public ?Edition $edition = null;

    public string $isbn = '';

    public string $title = '';

    public string $subtitle = '';

    /** Comma-separated names per contributor role (property = role in plural). */
    public string $authors = '';

    public string $translators = '';

    public string $compilers = '';

    public string $editors = '';

    public string $illustrators = '';

    public string $language = '';

    public string $publishedYear = '';

    public string $pageCount = '';

    /** @var list<string> */
    public array $genres = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $coverPhoto = null;

    public function mount(Publisher $publisher, ?Edition $edition = null): void
    {
        $this->authorize('manageBooks', $publisher);
        $this->publisher = $publisher;

        if ($edition === null) {
            return;
        }

        $edition->load('contributors', 'genres');
        $this->edition = $edition;
        $this->isbn = (string) ($edition->isbn_13 ?? $edition->isbn_10);
        $this->title = $edition->title;
        $this->subtitle = (string) $edition->subtitle;
        foreach (ContributorRole::cases() as $role) {
            $this->{$role->value.'s'} = $edition->contributors
                ->filter(fn ($person): bool => $person->pivot->role === $role->value)
                ->pluck('name')
                ->join(', ');
        }
        $this->language = (string) $edition->language;
        $this->publishedYear = (string) ($edition->published_year ?? '');
        $this->pageCount = (string) ($edition->page_count ?? '');
        $this->genres = $edition->genres->pluck('slug')->all();
    }

    /**
     * Every later request checks again, so a removed member loses access at once.
     */
    public function hydrate(): void
    {
        $this->authorize('manageBooks', $this->publisher);
    }

    public function save(): void
    {
        $this->validate([
            'isbn' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:500'],
            'translators' => ['nullable', 'string', 'max:500'],
            'compilers' => ['nullable', 'string', 'max:500'],
            'editors' => ['nullable', 'string', 'max:500'],
            'illustrators' => ['nullable', 'string', 'max:500'],
            'language' => ['required', Rule::in(Language::CODES)],
            'publishedYear' => ['nullable', 'integer', 'between:1450,'.(now()->year + 1)],
            'pageCount' => ['nullable', 'integer', 'between:1,20000'],
            'genres' => ['array', 'max:'.Edition::MAX_GENRES],
            'genres.*' => ['string', Rule::exists('genres', 'slug')],
            'coverPhoto' => ['nullable', 'mimes:jpeg,png,webp', 'max:10240', Rule::dimensions()->minWidth(100)->minHeight(100)->maxWidth(4200)->maxHeight(4200)],
        ]);

        $parsed = Isbn::parse($this->isbn);

        if ($parsed === null) {
            $this->addError('isbn', __('This is not a valid ISBN. Please check the digits.'));

            return;
        }

        $edition = $this->editionToSave($parsed);

        if ($edition === null) {
            return;
        }

        try {
            DB::transaction(function () use ($edition, $parsed): void {
                $edition->fill([
                    'title' => trim($this->title),
                    'subtitle' => trim($this->subtitle) ?: null,
                    'language' => $this->language,
                    'isbn_13' => $parsed['isbn13'],
                    'isbn_10' => $parsed['isbn10'],
                    'published_year' => $this->publishedYear !== '' ? (int) $this->publishedYear : null,
                    'page_count' => $this->pageCount !== '' ? (int) $this->pageCount : null,
                    'source' => EditionSource::Publisher,
                    'source_id' => null,
                ]);
                $edition->publisher()->associate($this->publisher);
                $edition->created_by ??= auth()->id();
                $edition->save();

                foreach (ContributorRole::cases() as $role) {
                    $edition->syncContributorNames($role, $this->{$role->value.'s'});
                }

                $edition->syncGenres($this->genres);
            });
        } catch (UniqueConstraintViolationException) {
            $this->addError('isbn', __('Another book already has this ISBN.'));

            return;
        }

        if ($this->coverPhoto !== null) {
            $path = app(CoverImage::class)->store($this->coverPhoto);

            if ($path === null) {
                $this->addError('coverPhoto', __('This image could not be read. Try another photo.'));

                return;
            }

            $edition->setUploadedCover($path);
        }

        session()->flash('status', __('“:title” saved.', ['title' => $edition->title]));
        $this->redirectRoute('publisher.books', $this->publisher, navigate: true);
    }

    /**
     * The edition to write: the one being edited, a new one, or a book readers
     * entered earlier with this ISBN, which the publisher now takes over.
     * Adds an ISBN error and returns null when the ISBN belongs to another book
     * that may not be taken over.
     *
     * @param  array{isbn13: string, isbn10: string|null}  $parsed
     */
    private function editionToSave(array $parsed): ?Edition
    {
        $existing = Edition::query()
            ->where(fn ($query) => $query->where('isbn_13', $parsed['isbn13'])
                ->when($parsed['isbn10'], fn ($inner) => $inner->orWhere('isbn_10', $parsed['isbn10'])))
            ->when($this->edition, fn ($query) => $query->whereKeyNot($this->edition->getKey()))
            ->first();

        if ($existing === null) {
            return $this->edition ?? new Edition;
        }

        $error = match (true) {
            $this->edition !== null => __('Another book already has this ISBN.'),
            $existing->isFromPublisher() && $existing->publisher_id === $this->publisher->id => __('This book is already in your list.'),
            $existing->isFromPublisher() || $this->isKeptByAnotherPublisher($existing) => __('This ISBN is in the list of another publisher. Write to us if the book is yours.'),
            default => null,
        };

        if ($error !== null) {
            $this->addError('isbn', $error);

            return null;
        }

        return $existing;
    }

    /**
     * Whether the edition names a publisher that has its own approved members.
     */
    private function isKeptByAnotherPublisher(Edition $edition): bool
    {
        return $edition->publisher_id !== null
            && $edition->publisher_id !== $this->publisher->id
            && PublisherMember::query()->where('publisher_id', $edition->publisher_id)->whereNotNull('approved_at')->exists();
    }

    public function render()
    {
        return $this->view()->title($this->edition ? __('Edit book') : __('Add a book'));
    }
};
?>

<div class="max-w-2xl">
    <a href="{{ route('publisher.books', $publisher) }}" wire:navigate class="link text-sm">← {{ $publisher->name }}</a>
    <h1 class="mt-2 font-serif text-3xl font-semibold">{{ $edition ? __('Edit book') : __('Add a book') }}</h1>

    @if ($edition && ! $edition->isFromPublisher())
        <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('Readers entered this book. Check the details and save: from then on only your publisher can change them.') }}</p>
    @endif

    <form wire:submit="save" class="mt-6 space-y-8">
        <section class="space-y-4">
            <div>
                <label for="isbn" class="form-label">{{ __('ISBN') }}</label>
                <input wire:model="isbn" id="isbn" type="text" inputmode="numeric" autocomplete="off" placeholder="978-9943-…" class="form-input">
                @error('isbn') <p class="form-error">{{ $message }}</p> @enderror
                @unless ($edition)
                    <p class="mt-1 text-sm text-ink-soft">{{ __('If readers already added this book, it moves to your list with your details.') }}</p>
                @endunless
            </div>

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
        </section>

        <section class="space-y-2">
            <h2 class="font-serif text-xl font-semibold">{{ __('Genre') }}</h2>
            <x-genre-picker model="genres" :selected="$genres" />
        </section>

        <section class="space-y-2">
            <h2 class="font-serif text-xl font-semibold">{{ __('Cover') }}</h2>
            <div class="flex items-start gap-4">
                @if ($coverPhoto)
                    <img src="{{ $coverPhoto->temporaryUrl() }}" alt="" class="h-28 w-auto rounded shadow-sm">
                @elseif ($edition?->hasCover())
                    <x-book-cover :edition="$edition" class="h-28 w-[4.67rem] shrink-0" />
                @endif
                <div>
                    <input wire:model="coverPhoto" id="coverPhoto" type="file" accept="image/jpeg,image/png,image/webp" class="text-sm">
                    <p class="mt-1 text-sm text-ink-soft">{{ __('A photo or scan of the front cover, JPEG, PNG or WebP.') }}</p>
                    @error('coverPhoto') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Save') }}</button>
            <a href="{{ route('publisher.books', $publisher) }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
