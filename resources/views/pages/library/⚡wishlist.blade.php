<?php

use App\Models\Library;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public const int MAX_ITEMS = 100;

    public string $title = '';

    public string $author = '';

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
     * @return Collection<int, WishlistItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->library->wishlistItems()->latest('id')->get();
    }

    public function add(): void
    {
        $this->authorize('updateWishlist', $this->library);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->items->count() >= self::MAX_ITEMS) {
            $this->addError('title', __('The wishlist is full. Remove a book first.'));

            return;
        }

        if ($this->items->contains('search_title', WishlistItem::titleKey($this->title))) {
            $this->addError('title', __('This book is already on your wishlist.'));

            return;
        }

        $this->library->wishlistItems()->create([
            'title' => trim($this->title),
            'author' => trim($this->author) ?: null,
        ]);

        $this->reset('title', 'author');
        unset($this->items);
    }

    public function remove(int $itemId): void
    {
        $this->authorize('updateWishlist', $this->library);

        $this->library->wishlistItems()->findOrFail($itemId)->delete();

        unset($this->items);
    }

    public function render()
    {
        return $this->view()->title(__('Wishlist'));
    }
};
?>

<div class="max-w-2xl">
    <h1 class="font-serif text-3xl font-semibold">{{ __('Wishlist') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('Books you would love to have. Friends see this list on your gift page, so they know what to give you.') }}</p>

    @unless ($this->library->isShared())
        <p class="mt-3 rounded-lg bg-paper px-4 py-3 text-sm text-ink-soft ring-1 ring-ink/10">
            {{ __('The gift page is off, so only members of this library can see the wishlist.') }}
            <a href="{{ route('library.members') }}#gift-page" class="link">{{ __('See how it works') }}</a>
        </p>
    @endunless

    @can('updateWishlist', $this->library)
        <form wire:submit="add" class="mt-6 space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="title" class="form-label">{{ __('Title') }}</label>
                    <input wire:model="title" id="title" type="text" required maxlength="255" class="form-input">
                    @error('title') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="author" class="form-label">{{ __('Author') }}</label>
                    <input wire:model="author" id="author" type="text" maxlength="255" class="form-input">
                    @error('author') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled" wire:target="add">{{ __('Add to wishlist') }}</button>
        </form>
    @endcan

    @if ($this->items->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-ink/20 px-6 py-12 text-center text-ink-soft">
            {{ __('No wishes yet.') }}
        </div>
    @else
        <ul class="mt-6 divide-y divide-ink/10 rounded-xl border border-ink/10 bg-white">
            @foreach ($this->items as $item)
                <li wire:key="wish-{{ $item->id }}" class="flex items-center justify-between gap-3 px-4 py-2">
                    <div class="min-w-0 py-1">
                        <p class="font-serif font-semibold leading-snug">{{ $item->title }}</p>
                        @if ($item->author)
                            <p class="text-sm text-ink-soft">{{ $item->author }}</p>
                        @endif
                    </div>
                    @can('updateWishlist', $this->library)
                        <button type="button" wire:click="remove({{ $item->id }})" class="min-h-11 shrink-0 cursor-pointer text-sm text-ink-soft underline hover:text-ink">{{ __('Remove') }}</button>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
</div>
