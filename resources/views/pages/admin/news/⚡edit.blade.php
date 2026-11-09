<?php

use App\Models\Author;
use App\Models\Edition;
use App\Models\Post;
use App\Models\Publisher;
use App\Services\CoverImage;
use App\Support\Concerns\RequiresSuperAdmin;
use App\Support\Concerns\SuggestsContributorNames;
use App\Support\Concerns\SuggestsPublisherNames;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use RequiresSuperAdmin, SuggestsContributorNames, SuggestsPublisherNames, WithFileUploads;

    public const int IMAGE_MAX_WIDTH = 1200;

    public const int IMAGE_MAX_HEIGHT = 800;

    #[Locked]
    public ?Post $post = null;

    public string $titleUz = '';

    public string $titleRu = '';

    public string $bodyUz = '';

    public string $bodyRu = '';

    /** Local time (app timezone) from a datetime-local input; empty = draft. */
    public string $publishedAt = '';

    /** What the post is about: an existing publisher, person or book (by ISBN). */
    public string $publisherName = '';

    public string $authorName = '';

    public string $bookIsbn = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $image = null;

    public function mount(?Post $post = null): void
    {
        if ($post === null || ! $post->exists) {
            return;
        }

        $post->load('publisher', 'author', 'edition');
        $this->post = $post;
        $this->titleUz = $post->title_uz;
        $this->titleRu = (string) $post->title_ru;
        $this->bodyUz = $post->body_uz;
        $this->bodyRu = (string) $post->body_ru;
        $this->publishedAt = $post->published_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i') ?? '';
        $this->publisherName = (string) $post->publisher?->name;
        $this->authorName = (string) $post->author?->name;
        $this->bookIsbn = (string) ($post->edition?->isbn_13 ?? $post->edition?->isbn_10);
    }

    public function save(): void
    {
        $this->store();
    }

    public function publishNow(): void
    {
        $this->publishedAt = now()->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        $this->store();
    }

    public function unpublish(): void
    {
        $this->publishedAt = '';
        $this->store();
    }

    public function removeImage(): void
    {
        $this->post?->setImage(null);
        session()->flash('status', __('Picture removed.'));
    }

    public function delete(): void
    {
        $this->post?->setImage(null);
        $this->post?->delete();

        session()->flash('status', __('Post deleted.'));
        $this->redirectRoute('admin.news.index', navigate: true);
    }

    private function store(): void
    {
        $this->validate([
            'titleUz' => ['required', 'string', 'max:255'],
            'titleRu' => ['nullable', 'string', 'max:255', 'required_with:bodyRu'],
            'bodyUz' => ['required', 'string', 'max:20000'],
            'bodyRu' => ['nullable', 'string', 'max:20000', 'required_with:titleRu'],
            'publishedAt' => ['nullable', 'date'],
            'publisherName' => ['nullable', 'string', 'max:255'],
            'authorName' => ['nullable', 'string', 'max:255'],
            'bookIsbn' => ['nullable', 'string', 'max:20'],
            'image' => ['nullable', 'mimes:jpeg,png,webp', 'max:10240', Rule::dimensions()->minWidth(300)->minHeight(200)->maxWidth(6000)->maxHeight(6000)],
        ], attributes: [
            'titleUz' => __('Title in Uzbek'),
            'titleRu' => __('Title in Russian'),
            'bodyUz' => __('Text in Uzbek'),
            'bodyRu' => __('Text in Russian'),
        ]);

        $related = $this->related();

        if ($related === null) {
            return;
        }

        $post = $this->post ?? new Post(['created_by' => auth()->id()]);
        $wasPublished = $post->exists && $post->isPublished();
        $post->fill([
            'title_uz' => trim($this->titleUz),
            'title_ru' => trim($this->titleRu) ?: null,
            'body_uz' => trim($this->bodyUz),
            'body_ru' => trim($this->bodyRu) ?: null,
            'published_at' => $this->publishedAt !== '' ? Carbon::parse($this->publishedAt, config('app.timezone'))->utc() : null,
            ...$related,
        ]);

        // The address stays the same once readers may have seen it.
        if (! $wasPublished) {
            $post->slug = Post::freeSlug($post->title_uz, $post->id);
        }

        $post->save();

        if ($this->image !== null) {
            $path = app(CoverImage::class)->store($this->image, 'news', self::IMAGE_MAX_WIDTH, self::IMAGE_MAX_HEIGHT);

            if ($path === null) {
                $this->addError('image', __('This image could not be read. Try another photo.'));
                $this->post = $post;

                return;
            }

            $post->setImage($path);
            $this->reset('image');
        }

        session()->flash('status', match (true) {
            $post->isPublished() => __('Post published.'),
            $post->published_at !== null => __('Post scheduled.'),
            default => __('Draft saved.'),
        });
        $this->redirectRoute('admin.news.edit', $post, navigate: true);
    }

    /**
     * The publisher, person and book the post is about, found by name or ISBN.
     * Adds an error and returns null when one of them is not in the catalog.
     *
     * @return array{publisher_id: int|null, author_id: int|null, edition_id: int|null}|null
     */
    private function related(): ?array
    {
        $publisher = trim($this->publisherName) !== '' ? Publisher::findByNameOrAlias($this->publisherName) : null;
        $author = trim($this->authorName) !== '' ? Author::findByNameOrAlias($this->authorName) : null;
        $edition = trim($this->bookIsbn) !== '' ? Edition::query()->whereIsbn($this->bookIsbn)->first() : null;

        foreach ([
            'publisherName' => [$this->publisherName, $publisher, __('There is no publisher with this name in the catalog.')],
            'authorName' => [$this->authorName, $author, __('There is no person with this name in the catalog.')],
            'bookIsbn' => [$this->bookIsbn, $edition, __('There is no book with this ISBN in the catalog.')],
        ] as $field => [$typed, $found, $error]) {
            if (trim($typed) !== '' && $found === null) {
                $this->addError($field, $error);
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        return [
            'publisher_id' => $publisher?->id,
            'author_id' => $author?->id,
            'edition_id' => $edition?->id,
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title($this->post ? __('Edit post') : __('New post'));
    }
};
?>

<div class="max-w-3xl">
    <x-flash class="mb-6" />

    <a href="{{ route('admin.news.index') }}" wire:navigate class="link text-sm">← {{ __('News') }}</a>
    <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-serif text-3xl font-semibold">{{ $post ? __('Edit post') : __('New post') }}</h1>
        @if ($post)
            <a href="{{ $post->publicUrl() }}" class="link text-sm">{{ $post->isPublished() ? __('Open') : __('Preview') }}</a>
        @endif
    </div>

    <form wire:submit="save" class="mt-6 space-y-8">
        <section class="space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('Uzbek') }}</h2>
            <div>
                <label for="titleUz" class="form-label">{{ __('Title') }}</label>
                <input wire:model="titleUz" id="titleUz" type="text" maxlength="255" class="form-input">
                @error('titleUz') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="bodyUz" class="form-label">{{ __('Text') }}</label>
                <textarea wire:model="bodyUz" id="bodyUz" rows="10" maxlength="20000" class="form-input font-mono text-sm"></textarea>
                <p class="mt-1 text-xs text-ink-soft">{{ __('Empty line = new paragraph. **bold**, *italic*, [link text](https://…), lines starting with "- " make a list.') }}</p>
                @error('bodyUz') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('Russian') }} <span class="text-sm font-normal text-ink-soft">{{ __('(optional; without it Russian readers see the Uzbek text)') }}</span></h2>
            <div>
                <label for="titleRu" class="form-label">{{ __('Title') }}</label>
                <input wire:model="titleRu" id="titleRu" type="text" maxlength="255" class="form-input">
                @error('titleRu') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="bodyRu" class="form-label">{{ __('Text') }}</label>
                <textarea wire:model="bodyRu" id="bodyRu" rows="10" maxlength="20000" class="form-input font-mono text-sm"></textarea>
                @error('bodyRu') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('Picture') }}</h2>
            <div class="flex flex-wrap items-start gap-4">
                @if ($image)
                    <img src="{{ $image->temporaryUrl() }}" alt="" class="h-32 w-auto rounded">
                @elseif ($post?->image_url)
                    <img src="{{ $post->image_url }}" alt="" class="h-32 w-auto rounded">
                @endif
                <div>
                    <input wire:model="image" id="image" type="file" accept="image/jpeg,image/png,image/webp" class="text-sm">
                    <p class="mt-1 text-xs text-ink-soft">{{ __('Landscape works best. It is resized to :width px wide.', ['width' => self::IMAGE_MAX_WIDTH]) }}</p>
                    @error('image') <p class="form-error">{{ $message }}</p> @enderror
                    @if ($post?->image_path && ! $image)
                        <button type="button" wire:click="removeImage" class="mt-2 text-sm text-red-700 hover:underline">{{ __('Remove picture') }}</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('About') }} <span class="text-sm font-normal text-ink-soft">{{ __('(optional; the post also appears on these pages)') }}</span></h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="publisherName" class="form-label">{{ __('Publisher') }}</label>
                    <x-publisher-input id="publisherName" wire:model="publisherName" />
                    @error('publisherName') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="authorName" class="form-label">{{ __('Person') }}</label>
                    <x-people-input id="authorName" wire:model="authorName" />
                    @error('authorName') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="bookIsbn" class="form-label">{{ __('Book ISBN') }}</label>
                    <input wire:model="bookIsbn" id="bookIsbn" type="text" inputmode="numeric" maxlength="20" class="form-input">
                    @error('bookIsbn') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('Publishing') }}</h2>
            <div>
                <label for="publishedAt" class="form-label">{{ __('Publish at') }}</label>
                <input wire:model="publishedAt" id="publishedAt" type="datetime-local" class="form-input sm:max-w-xs">
                <p class="mt-1 text-xs text-ink-soft">{{ __('Empty = draft, seen only by super admins. A future time = the post appears by itself then.') }}</p>
                @error('publishedAt') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-primary w-auto" wire:loading.attr="disabled">{{ __('Save') }}</button>
                @unless ($post?->isPublished())
                    <button type="button" wire:click="publishNow" wire:loading.attr="disabled" class="min-h-11 cursor-pointer rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white">{{ __('Publish now') }}</button>
                @else
                    <button type="button" wire:click="unpublish" class="text-sm text-ink-soft hover:text-ink">{{ __('Back to draft') }}</button>
                @endunless
                @if ($post)
                    <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this post for good?') }}" class="ml-auto text-sm text-red-700 hover:underline">{{ __('Delete') }}</button>
                @endif
            </div>
        </section>
    </form>
</div>
