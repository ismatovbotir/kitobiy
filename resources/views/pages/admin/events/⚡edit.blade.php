<?php

use App\Models\BookEvent;
use App\Services\CoverImage;
use App\Support\Concerns\PicksRelatedCatalogRecords;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use PicksRelatedCatalogRecords, RequiresSuperAdmin, WithFileUploads;

    public const int IMAGE_MAX_WIDTH = 1200;

    public const int IMAGE_MAX_HEIGHT = 800;

    #[Locked]
    public ?BookEvent $event = null;

    public string $titleUz = '';

    public string $titleRu = '';

    public string $bodyUz = '';

    public string $bodyRu = '';

    /** Local times (app timezone) from datetime-local inputs. */
    public string $startsAt = '';

    public string $endsAt = '';

    public string $place = '';

    public string $city = '';

    public string $onlineUrl = '';

    public string $infoUrl = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $image = null;

    public function mount(?BookEvent $bookEvent = null): void
    {
        if ($bookEvent === null || ! $bookEvent->exists) {
            return;
        }

        $bookEvent->load('publisher', 'author', 'edition');
        $this->event = $bookEvent;
        $this->titleUz = $bookEvent->title_uz;
        $this->titleRu = (string) $bookEvent->title_ru;
        $this->bodyUz = $bookEvent->body_uz;
        $this->bodyRu = (string) $bookEvent->body_ru;
        $this->startsAt = $bookEvent->startsAtLocal()->format('Y-m-d\TH:i');
        $this->endsAt = $bookEvent->ends_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i') ?? '';
        $this->place = (string) $bookEvent->place;
        $this->city = (string) $bookEvent->city;
        $this->onlineUrl = (string) $bookEvent->online_url;
        $this->infoUrl = (string) $bookEvent->info_url;
        $this->fillRelatedFrom($bookEvent);
    }

    public function save(): void
    {
        $this->store($this->event?->is_published ?? false);
    }

    public function publish(): void
    {
        $this->store(true);
    }

    public function unpublish(): void
    {
        $this->store(false);
    }

    public function removeImage(): void
    {
        $this->event?->setImage(null);
        session()->flash('status', __('Picture removed.'));
    }

    public function delete(): void
    {
        $this->event?->setImage(null);
        $this->event?->delete();

        session()->flash('status', __('Event deleted.'));
        $this->redirectRoute('admin.events.index', navigate: true);
    }

    private function store(bool $isPublished): void
    {
        $this->validate([
            'titleUz' => ['required', 'string', 'max:255'],
            'titleRu' => ['nullable', 'string', 'max:255', 'required_with:bodyRu'],
            'bodyUz' => ['required', 'string', 'max:20000'],
            'bodyRu' => ['nullable', 'string', 'max:20000', 'required_with:titleRu'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
            'place' => ['nullable', 'string', 'max:255', 'required_without_all:city,onlineUrl'],
            'city' => ['nullable', 'string', 'max:100'],
            'onlineUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'infoUrl' => ['nullable', 'url:http,https', 'max:2048'],
            ...$this->relatedRules(),
            'image' => ['nullable', 'mimes:jpeg,png,webp', 'max:10240', Rule::dimensions()->minWidth(300)->minHeight(200)->maxWidth(6000)->maxHeight(6000)],
        ], [
            'place.required_without_all' => __('Give a place, a city or an online link.'),
        ], [
            'titleUz' => __('Title in Uzbek'),
            'titleRu' => __('Title in Russian'),
            'bodyUz' => __('Text in Uzbek'),
            'bodyRu' => __('Text in Russian'),
            'startsAt' => __('Starts'),
            'endsAt' => __('Ends'),
        ]);

        $related = $this->relatedCatalogIds();

        if ($related === null) {
            return;
        }

        $zone = config('app.timezone');
        $event = $this->event ?? new BookEvent(['created_by' => auth()->id()]);
        $wasPublished = $event->exists && $event->is_published;
        $event->fill([
            'title_uz' => trim($this->titleUz),
            'title_ru' => trim($this->titleRu) ?: null,
            'body_uz' => trim($this->bodyUz),
            'body_ru' => trim($this->bodyRu) ?: null,
            'starts_at' => Carbon::parse($this->startsAt, $zone)->utc(),
            'ends_at' => $this->endsAt !== '' ? Carbon::parse($this->endsAt, $zone)->utc() : null,
            'place' => trim($this->place) ?: null,
            'city' => trim($this->city) ?: null,
            'online_url' => trim($this->onlineUrl) ?: null,
            'info_url' => trim($this->infoUrl) ?: null,
            'is_published' => $isPublished,
            ...$related,
        ]);

        // The address stays the same once readers may have seen it.
        if (! $wasPublished) {
            $event->slug = BookEvent::freeSlug($event->title_uz, $event->id);
        }

        $event->save();

        if ($this->image !== null) {
            $path = app(CoverImage::class)->store($this->image, 'events', self::IMAGE_MAX_WIDTH, self::IMAGE_MAX_HEIGHT);

            if ($path === null) {
                $this->addError('image', __('This image could not be read. Try another photo.'));
                $this->event = $event;

                return;
            }

            $event->setImage($path);
            $this->reset('image');
        }

        session()->flash('status', $event->is_published ? __('Event published.') : __('Draft saved.'));
        $this->redirectRoute('admin.events.edit', $event, navigate: true);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title($this->event ? __('Edit event') : __('New event'));
    }
};
?>

<div class="max-w-3xl">
    <x-flash class="mb-6" />

    <a href="{{ route('admin.events.index') }}" wire:navigate class="link text-sm">← {{ __('Book events') }}</a>
    <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-serif text-3xl font-semibold">{{ $event ? __('Edit event') : __('New event') }}</h1>
        @if ($event)
            <a href="{{ $event->publicUrl() }}" class="link text-sm">{{ $event->is_published ? __('Open') : __('Preview') }}</a>
        @endif
    </div>

    <form wire:submit="save" class="mt-6 space-y-8">
        <section class="space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('When and where') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="startsAt" class="form-label">{{ __('Starts') }}</label>
                    <input wire:model="startsAt" id="startsAt" type="datetime-local" class="form-input">
                    @error('startsAt') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="endsAt" class="form-label">{{ __('Ends') }} <span class="font-normal text-ink-soft">{{ __('(optional)') }}</span></label>
                    <input wire:model="endsAt" id="endsAt" type="datetime-local" class="form-input">
                    @error('endsAt') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="place" class="form-label">{{ __('Place') }}</label>
                    <input wire:model="place" id="place" type="text" maxlength="255" placeholder="{{ __('e.g. National Library, hall 2') }}" class="form-input">
                    @error('place') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="city" class="form-label">{{ __('City') }}</label>
                    <input wire:model="city" id="city" type="text" maxlength="100" class="form-input">
                    @error('city') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="onlineUrl" class="form-label">{{ __('Online link') }} <span class="font-normal text-ink-soft">{{ __('(optional)') }}</span></label>
                    <input wire:model="onlineUrl" id="onlineUrl" type="url" maxlength="2048" placeholder="https://" class="form-input">
                    @error('onlineUrl') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="infoUrl" class="form-label">{{ __('Details or registration link') }} <span class="font-normal text-ink-soft">{{ __('(optional)') }}</span></label>
                    <input wire:model="infoUrl" id="infoUrl" type="url" maxlength="2048" placeholder="https://" class="form-input">
                    @error('infoUrl') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('Uzbek') }}</h2>
            <div>
                <label for="titleUz" class="form-label">{{ __('Title') }}</label>
                <input wire:model="titleUz" id="titleUz" type="text" maxlength="255" class="form-input">
                @error('titleUz') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="bodyUz" class="form-label">{{ __('Text') }}</label>
                <textarea wire:model="bodyUz" id="bodyUz" rows="8" maxlength="20000" class="form-input font-mono text-sm"></textarea>
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
                <textarea wire:model="bodyRu" id="bodyRu" rows="8" maxlength="20000" class="form-input font-mono text-sm"></textarea>
                @error('bodyRu') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('Picture') }}</h2>
            <div class="flex flex-wrap items-start gap-4">
                @if ($image)
                    <img src="{{ $image->temporaryUrl() }}" alt="" class="h-32 w-auto rounded">
                @elseif ($event?->image_url)
                    <img src="{{ $event->image_url }}" alt="" class="h-32 w-auto rounded">
                @endif
                <div>
                    <input wire:model="image" id="image" type="file" accept="image/jpeg,image/png,image/webp" class="text-sm">
                    <p class="mt-1 text-xs text-ink-soft">{{ __('Landscape works best. It is resized to :width px wide.', ['width' => self::IMAGE_MAX_WIDTH]) }}</p>
                    @error('image') <p class="form-error">{{ $message }}</p> @enderror
                    @if ($event?->image_path && ! $image)
                        <button type="button" wire:click="removeImage" class="mt-2 text-sm text-red-700 hover:underline">{{ __('Remove picture') }}</button>
                    @endif
                </div>
            </div>
        </section>

        <x-admin-related-fields :hint="__('(optional; the event also appears on these pages)')" />

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary w-auto" wire:loading.attr="disabled">{{ __('Save') }}</button>
            @if ($event?->is_published)
                <button type="button" wire:click="unpublish" class="text-sm text-ink-soft hover:text-ink">{{ __('Back to draft') }}</button>
            @else
                <button type="button" wire:click="publish" wire:loading.attr="disabled" class="min-h-11 cursor-pointer rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white">{{ __('Publish') }}</button>
            @endif
            @if ($event)
                <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this event for good?') }}" class="ml-auto text-sm text-red-700 hover:underline">{{ __('Delete') }}</button>
            @endif
        </div>
    </form>
</div>
