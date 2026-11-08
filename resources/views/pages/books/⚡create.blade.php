<?php

use App\Enums\ContributorRole;
use App\Enums\EditionSource;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Publisher;
use App\Services\GoogleBooks;
use App\Services\OpenLibrary;
use App\Support\Concerns\SuggestsContributorNames;
use App\Support\Concerns\SuggestsPublisherNames;
use App\Support\Isbn;
use App\Support\Language;
use App\Support\SearchKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;

new class extends Component
{
    use SuggestsContributorNames, SuggestsPublisherNames;

    /** 'search' = ISBN entry, 'details' = check the book and place the copy. */
    public string $step = 'search';

    public string $isbn = '';

    public ?string $lookupMessage = null;

    #[Locked]
    public ?int $editionId = null;

    #[Locked]
    public ?string $isbn13 = null;

    #[Locked]
    public ?string $isbn10 = null;

    #[Locked]
    public string $source = 'manual';

    #[Locked]
    public ?string $sourceId = null;

    #[Locked]
    public ?string $coverUrl = null;

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

    /** @var list<string> Genre slugs for a book that has no genres yet. */
    public array $genres = [];

    /** @var list<string> */
    public array $tagIds = [];

    public string $newTag = '';

    public string $notes = '';

    /**
     * Scanning a shelf: a book already in the catalog is added at once, without
     * the details step, and the camera goes on to the next one. Remembered per session.
     */
    #[Session]
    public bool $quickAdd = false;

    /**
     * Viewers only look at the books, so they can't open this page.
     */
    public function mount(): void
    {
        $this->authorize('addBooks', $this->library);
    }

    #[Computed]
    public function library(): Library
    {
        return auth()->user()->libraryOrCreateDefault();
    }

    #[Computed]
    public function edition(): ?Edition
    {
        return $this->editionId ? Edition::with(['authors', 'contributors', 'genres', 'publisher'])->find($this->editionId) : null;
    }

    /**
     * Genres can be chosen for a new book, or for a catalog book nobody has given genres yet.
     */
    #[Computed]
    public function canChooseGenres(): bool
    {
        return $this->edition === null || $this->edition->genres->isEmpty();
    }

    #[Computed]
    public function tags(): Collection
    {
        return $this->library->tags()->orderBy('name')->get();
    }

    /**
     * How many copies of the chosen catalog edition this library already has.
     */
    #[Computed]
    public function copiesAlreadyOwned(): int
    {
        return $this->editionId ? $this->library->copies()->where('edition_id', $this->editionId)->count() : 0;
    }

    /**
     * Copies in this library with a similar title, to catch duplicates of books
     * without an ISBN (common for local prints).
     *
     * @return Collection<int, Copy>
     */
    #[Computed]
    public function similarCopies(): Collection
    {
        $title = trim($this->title);

        if ($this->editionId !== null || mb_strlen($title) < 3) {
            return new Collection;
        }

        $pattern = SearchKey::containsPattern($title);

        if ($pattern === null) {
            return new Collection;
        }

        return $this->library->copies()
            ->whereHas('edition', fn ($query) => $query->whereLike('search_text', $pattern))
            ->with('edition')
            ->limit(5)
            ->get();
    }

    /**
     * Find the book: our catalog first, then Open Library, then Google Books,
     * else manual entry.
     */
    public function lookup(OpenLibrary $openLibrary, GoogleBooks $googleBooks): void
    {
        $this->validate(['isbn' => ['required', 'string', 'max:20']]);

        $parsed = Isbn::parse($this->isbn);

        if ($parsed === null) {
            $this->addError('isbn', __('This is not a valid ISBN. Please check the digits.'));

            return;
        }

        $this->resetBook();
        $this->isbn13 = $parsed['isbn13'];
        $this->isbn10 = $parsed['isbn10'];
        $this->step = 'details';

        $edition = $this->findEditionByIsbn();

        if ($edition !== null) {
            $this->editionId = $edition->id;

            if ($this->quickAdd && $this->copiesAlreadyOwned === 0) {
                $this->authorize('addBooks', $this->library);
                $this->validate($this->copyRules());
                $this->addCopyOf($edition);
                $this->dispatch('book-added-quickly');
            }

            return;
        }

        $source = EditionSource::OpenLibrary;
        $book = $openLibrary->findByIsbn($parsed['isbn13']);

        if ($book === null) {
            $source = EditionSource::GoogleBooks;
            $book = $googleBooks->findByIsbn($parsed['isbn13']);
        }

        if ($book === null) {
            $this->lookupMessage = __('We could not find this ISBN. Please enter the details — the next reader will find it.');

            return;
        }

        $this->source = $source->value;
        $this->sourceId = $book['source_id'];
        $this->coverUrl = $book['cover_url'];
        $this->title = $book['title'];
        $this->subtitle = $book['subtitle'] ?? '';
        $this->authors = implode(', ', $book['authors']);
        $this->publisher = $book['publisher'] ?? '';
        $this->publishedYear = (string) ($book['published_year'] ?? '');
        $this->pageCount = (string) ($book['page_count'] ?? '');
        $this->language = $book['language'] ?? '';
        $this->lookupMessage = $this->language !== ''
            ? __('Found on :source. Check the details.', ['source' => $source->label()])
            : __('Found on :source. Check the details and choose the language.', ['source' => $source->label()]);
    }

    public function addWithoutIsbn(): void
    {
        $this->resetBook();
        $this->resetErrorBag();
        $this->step = 'details';
    }

    public function backToSearch(): void
    {
        $this->resetBook();
        $this->resetErrorBag();
        $this->step = 'search';
    }

    public function save(): void
    {
        $this->authorize('addBooks', $this->library);

        $this->validate([
            ...($this->editionId === null ? [
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
            ] : []),
            'genres' => ['array', 'max:'.Edition::MAX_GENRES],
            'genres.*' => ['string', Rule::exists('genres', 'slug')->where('is_active', true)],
            ...$this->copyRules(),
        ]);

        $this->addCopyOf(DB::transaction(function (): Edition {
            $edition = $this->edition ?? $this->findEditionByIsbn() ?? $this->createEdition();

            if ($this->genres !== [] && $edition->genres()->doesntExist()) {
                $edition->syncGenres($this->genres);
            }

            return $edition;
        }));
    }

    /**
     * Rules for what belongs to the copy: its tags and notes.
     *
     * @return array<string, array<int, mixed>>
     */
    private function copyRules(): array
    {
        return [
            'tagIds' => ['array'],
            'tagIds.*' => ['integer', Rule::exists('tags', 'id')->where('library_id', $this->library->id)],
            'newTag' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Put a copy of the edition on the shelf with the chosen tags and notes,
     * then go back to the ISBN step for the next book.
     */
    private function addCopyOf(Edition $edition): void
    {
        $copy = DB::transaction(function () use ($edition): Copy {
            $tagIds = array_map('intval', $this->tagIds);

            if (trim($this->newTag) !== '') {
                $tagIds[] = $this->library->tags()->firstOrCreate(['name' => trim($this->newTag)])->id;
            }

            $copy = $this->library->copies()->create([
                'edition_id' => $edition->id,
                'added_by' => auth()->id(),
                'notes' => trim($this->notes) ?: null,
            ]);

            $copy->tags()->sync(array_unique($tagIds));

            return $copy;
        });

        session()->flash('status', __(
            $this->library->removeWishesFulfilledBy($copy->edition) > 0
                ? '“:title” added to your library and removed from your wishlist.'
                : '“:title” added to your library.',
            ['title' => $copy->edition->title],
        ));

        // Keep the tags: people often add several books of one kind in a row.
        $this->tagIds = $copy->tags()->pluck('tags.id')->map(fn (int $id): string => (string) $id)->all();
        $this->reset('newTag', 'notes', 'isbn');
        unset($this->tags);
        $this->backToSearch();
    }

    private function findEditionByIsbn(): ?Edition
    {
        if ($this->isbn13 === null) {
            return null;
        }

        return Edition::query()
            ->where('isbn_13', $this->isbn13)
            ->when($this->isbn10, fn ($query) => $query->orWhere('isbn_10', $this->isbn10))
            ->first();
    }

    /**
     * Save the entered book into the shared catalog. A concurrent insert of the
     * same ISBN is resolved by reusing the edition that won.
     */
    private function createEdition(): Edition
    {
        try {
            return DB::transaction(function (): Edition {
                $edition = Edition::create([
                    'title' => trim($this->title),
                    'subtitle' => trim($this->subtitle) ?: null,
                    'language' => $this->language ?: null,
                    'isbn_13' => $this->isbn13,
                    'isbn_10' => $this->isbn10,
                    'publisher_id' => Publisher::findOrCreateByName($this->publisher)?->id,
                    'published_year' => $this->publishedYear !== '' ? (int) $this->publishedYear : null,
                    'page_count' => $this->pageCount !== '' ? (int) $this->pageCount : null,
                    'cover_url' => $this->coverUrl,
                    'source' => $this->source,
                    'source_id' => $this->freeSourceId(),
                    'created_by' => auth()->id(),
                ]);

                foreach (ContributorRole::cases() as $role) {
                    $edition->syncContributorNames($role, $this->{$role->value.'s'});
                }

                return $edition;
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->findEditionByIsbn() ?? throw $exception;
        }
    }

    /**
     * The looked-up record id, unless another edition already holds it (two
     * ISBNs of one book can point to the same Open Library / Google record).
     */
    private function freeSourceId(): ?string
    {
        if ($this->source === EditionSource::Manual->value || $this->sourceId === null) {
            return null;
        }

        $isTaken = Edition::query()
            ->where('source', $this->source)
            ->where('source_id', $this->sourceId)
            ->exists();

        return $isTaken ? null : $this->sourceId;
    }

    private function resetBook(): void
    {
        $this->reset(
            'editionId', 'isbn13', 'isbn10', 'source', 'sourceId', 'coverUrl', 'lookupMessage', 'genres',
            'title', 'subtitle', 'authors', 'translators', 'compilers', 'editors', 'illustrators', 'language', 'publisher', 'publishedYear', 'pageCount',
        );
    }

    public function render()
    {
        return $this->view()->title(__('Add a book'));
    }
};
?>

<div class="max-w-2xl">
    <a href="{{ route('dashboard') }}" wire:navigate class="link text-sm">← {{ $this->library->name }}</a>
    <h1 class="mt-2 font-serif text-3xl font-semibold">{{ __('Add a book') }}</h1>

    <x-flash class="mt-4" />

    @if ($step === 'search')
        <form wire:submit="lookup" class="mt-6 space-y-4">
            <div>
                <label for="isbn" class="form-label">{{ __('ISBN') }}</label>
                <div class="flex gap-2">
                    <input wire:model="isbn" id="isbn" type="text" inputmode="numeric" autocomplete="off" autofocus
                           placeholder="978…" class="form-input">
                    <button type="submit" class="btn-primary w-auto shrink-0" wire:loading.attr="disabled" wire:target="lookup">
                        <span wire:loading.remove wire:target="lookup">{{ __('Find') }}</span>
                        <span wire:loading wire:target="lookup">{{ __('Searching…') }}</span>
                    </button>
                </div>
                @error('isbn') <p class="form-error">{{ $message }}</p> @enderror
                <p class="mt-1 text-sm text-ink-soft">{{ __('The ISBN is printed on the back cover above the barcode, or on the copyright page.') }}</p>
            </div>
        </form>

        {{-- Camera scanning: built-in BarcodeDetector where available, zxing-wasm fallback elsewhere (resources/js/barcode.js). Needs HTTPS. --}}
        {{-- After a quick add the camera starts again; the book just added is ignored for a few seconds, as it is still in view. --}}
        <div wire:ignore class="mt-4"
             x-on:book-added-quickly.window="navigator.vibrate?.(80); start()"
             x-data="{
                supported: !! window.Kitobiy?.canScanBarcodes(),
                scanning: false,
                failed: false,
                stream: null,
                lastIsbn: null,
                lastIsbnAt: 0,
                async start() {
                    this.failed = false;
                    this.scanning = true;
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                        this.$refs.video.srcObject = this.stream;
                        await this.$refs.video.play();
                        const detector = await window.Kitobiy.createIsbnDetector();
                        const tick = async () => {
                            if (! this.scanning) return;
                            const codes = await detector.detect(this.$refs.video).catch(() => []);
                            const isbn = codes.map(code => code.rawValue).find(value => /^97[89]\d{10}$/.test(value)
                                && ! (value === this.lastIsbn && Date.now() - this.lastIsbnAt < 4000));
                            if (isbn) {
                                this.lastIsbn = isbn;
                                this.lastIsbnAt = Date.now();
                                this.stop();
                                $wire.isbn = isbn;
                                $wire.lookup();
                                return;
                            }
                            setTimeout(tick, 250);
                        };
                        tick();
                    } catch (error) {
                        this.stop();
                        this.failed = true;
                    }
                },
                stop() {
                    this.scanning = false;
                    this.stream?.getTracks().forEach(track => track.stop());
                    this.stream = null;
                },
                destroy() { this.stop(); },
             }">
            <template x-if="supported">
                <div>
                    <button type="button" x-show="! scanning" x-on:click="start()" class="link text-sm">📷 {{ __('Scan the barcode with the camera') }}</button>
                    <div x-show="scanning" x-cloak class="space-y-2">
                        <video x-ref="video" playsinline muted class="w-full max-w-sm rounded-lg border border-ink/10 bg-ink"></video>
                        <button type="button" x-on:click="stop()" class="text-sm text-ink-soft hover:text-ink">{{ __('Stop scanning') }}</button>
                    </div>
                    <p x-show="failed" x-cloak class="form-error">{{ __('Could not open the camera. Type the ISBN instead.') }}</p>
                </div>
            </template>
        </div>

        <label class="mt-6 flex items-start gap-2 text-sm">
            <input wire:model.live="quickAdd" type="checkbox" class="mt-0.5 rounded border-ink/30 text-accent">
            <span>
                <span class="font-medium">{{ __('Add straight away') }}</span>
                <span class="block text-ink-soft">{{ __('Books already in Kitobiy are added without the details step, so you can scan a whole shelf. New books and books you already have still open the details.') }}</span>
            </span>
        </label>
        @if ($quickAdd && $tagIds !== [])
            <p class="mt-2 ml-6 text-sm text-ink-soft">{{ __('Tags for these books: :tags', ['tags' => $this->tags->whereIn('id', array_map('intval', $tagIds))->pluck('name')->join(', ')]) }}</p>
        @endif

        <p class="mt-8 text-sm text-ink-soft">
            {{ __('No ISBN on the book?') }}
            <button type="button" wire:click="addWithoutIsbn" class="link">{{ __('Enter it by hand') }}</button>
        </p>
    @else
        <form wire:submit="save" class="mt-6 space-y-8">
            @if ($lookupMessage)
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $lookupMessage }}</p>
            @endif

            @if ($this->edition)
                <section class="flex gap-4 rounded-lg border border-ink/10 bg-white p-4">
                    @if ($this->edition->cover_image_url)
                        <img src="{{ $this->edition->cover_image_url }}" alt="" class="h-28 w-auto rounded shadow-sm">
                    @endif
                    <div>
                        <p class="font-serif text-xl font-semibold">{{ $this->edition->title }}</p>
                        @if ($this->edition->subtitle)
                            <p class="text-ink-soft">{{ $this->edition->subtitle }}</p>
                        @endif
                        <p class="mt-1 text-sm">{{ $this->edition->authors->pluck('name')->join(', ') }}</p>
                        <p class="mt-1 text-sm text-ink-soft">
                            {{ collect([$this->edition->publisher?->name, $this->edition->published_year, $this->edition->isbn_13])->filter()->join(' · ') }}
                        </p>
                    </div>
                </section>

                @if ($this->copiesAlreadyOwned > 0)
                    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-800">
                        {{ trans_choice('You already have this book (:count copy).|You already have this book (:count copies).', $this->copiesAlreadyOwned) }}
                    </p>
                @endif
            @else
                <section class="space-y-4">
                    <div class="flex gap-4">
                        @if ($coverUrl)
                            <img src="{{ $coverUrl }}" alt="" class="h-28 w-auto rounded shadow-sm">
                        @endif
                        <div class="flex-1">
                            <label for="title" class="form-label">{{ __('Title') }}</label>
                            <input wire:model.blur="title" id="title" type="text" required maxlength="255" class="form-input">
                            @error('title') <p class="form-error">{{ $message }}</p> @enderror
                            @if ($isbn13)
                                <p class="mt-1 text-sm text-ink-soft">ISBN {{ $isbn13 }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($this->similarCopies->isNotEmpty())
                        <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            <p class="font-medium">{{ __('Similar books already in your library:') }}</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($this->similarCopies as $similar)
                                    <li wire:key="similar-{{ $similar->id }}">{{ $similar->edition->title }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

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
                </section>
            @endif

            <section class="space-y-2">
                <h2 class="font-serif text-xl font-semibold">{{ __('Genre') }}</h2>
                @if ($this->canChooseGenres)
                    <x-genre-picker model="genres" />
                @else
                    <x-genre-chips :genres="$this->edition->genres" />
                @endif
            </section>

            <section class="space-y-4">
                <h2 class="font-serif text-xl font-semibold">{{ __('About your copy') }}</h2>

                <div>
                    <span class="form-label">{{ __('Tags') }}</span>
                    @if ($this->tags->isNotEmpty())
                        <div class="mb-2 flex flex-wrap gap-2">
                            @foreach ($this->tags as $tag)
                                <label wire:key="tag-{{ $tag->id }}" class="inline-flex items-center gap-1.5 rounded-full border border-ink/15 bg-white px-3 py-1 text-sm">
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
            </section>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Add to library') }}</button>
                <button type="button" wire:click="backToSearch" class="text-sm text-ink-soft hover:text-ink">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif
</div>
