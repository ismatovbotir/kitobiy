<?php

use App\Models\Copy;
use App\Models\FoundReport;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The public page of one physical book, opened from the QR code on its label.
 * It works without an account. It shows the book and, only if the library allows
 * it, the library's name; never notes, tags, members or anyone's contact details.
 */
new class extends Component
{
    #[Locked]
    public string $publicId = '';

    public string $body = '';

    public string $contact = '';

    /** Hidden from people; only bots fill it in. */
    public string $website = '';

    public bool $sent = false;

    public function mount(string $publicId): void
    {
        $this->publicId = $publicId;

        abort_if($this->copy === null, 404);
    }

    #[Computed]
    public function copy(): ?Copy
    {
        return Copy::query()
            ->where('public_id', strtolower($this->publicId))
            ->with(['edition.authors', 'library'])
            ->first();
    }

    public function send(): void
    {
        $this->validate([
            'body' => ['required', 'string', 'max:1000'],
            'contact' => ['nullable', 'string', 'max:120'],
        ]);

        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $visitorKey = 'found-visitor:'.request()->ip();
        $bookKey = 'found-book:'.$this->copy->id;

        if (RateLimiter::tooManyAttempts($visitorKey, FoundReport::MAX_PER_VISITOR_PER_HOUR)
            || RateLimiter::tooManyAttempts($bookKey, FoundReport::MAX_PER_BOOK_PER_HOUR)) {
            $this->addError('body', __('Too many messages. Please try again later.'));

            return;
        }

        RateLimiter::hit($visitorKey, 3600);
        RateLimiter::hit($bookKey, 3600);

        FoundReport::send($this->copy, trim($this->body), trim($this->contact) ?: null);

        $this->reset('body', 'contact');
        $this->sent = true;
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::public')
            ->title($this->copy?->edition->title ?? '');
    }
};
?>

<div class="max-w-2xl">
    <section class="flex gap-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <div class="w-20 shrink-0 self-start">
            <x-book-cover :edition="$this->copy->edition" class="w-full" />
        </div>
        <div class="min-w-0">
            <h1 class="font-serif text-2xl font-semibold leading-tight">{{ $this->copy->edition->title }}</h1>
            @if ($this->copy->edition->authors->isNotEmpty())
                <p class="mt-1">{{ $this->copy->edition->authors->pluck('name')->join(', ') }}</p>
            @endif
            @if ($this->copy->library->show_name_to_finders)
                <p class="mt-2 text-sm text-ink-soft">{{ __('From the library “:library”.', ['library' => $this->copy->library->name]) }}</p>
            @endif
        </div>
    </section>

    @if ($this->copy->isLost())
        <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('The owner is looking for this book.') }}</p>
    @endif

    @if ($sent)
        <p class="mt-6 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 ring-1 ring-green-200" role="status">{{ __('Thank you! Your message was sent to the owner of this book.') }}</p>
    @else
        <form wire:submit="send" class="mt-6 space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <div>
                <h2 class="font-serif text-xl font-semibold">{{ __('Did you find this book?') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Write to its owner. Your message goes only to them.') }}</p>
            </div>
            <div>
                <label for="body" class="form-label">{{ __('Your message') }}</label>
                <textarea wire:model="body" id="body" rows="3" maxlength="1000" required placeholder="{{ __('e.g. I found it on a bench in the park') }}" class="form-input"></textarea>
                @error('body') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="contact" class="form-label">{{ __('How can they reach you? (optional)') }}</label>
                <input wire:model="contact" id="contact" type="text" maxlength="120" autocomplete="off" placeholder="{{ __('Phone or Telegram') }}" class="form-input">
                <p class="mt-1 text-xs text-ink-soft">{{ __('Only the people who run this library will see it.') }}</p>
                @error('contact') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="website">Website</label>
                <input wire:model="website" id="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Send') }}</button>
        </form>
    @endif
</div>
