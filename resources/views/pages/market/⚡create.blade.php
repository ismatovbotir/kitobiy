<?php

use App\Enums\ListingKind;
use App\Models\Copy;
use App\Rules\NoContactDetails;
use App\Support\Concerns\RequiresMarket;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use RequiresMarket;

    public Copy $copy;

    public string $kind = 'sell';

    public string $price = '';

    public string $note = '';

    public function mount(Copy $copy): void
    {
        $this->authorize('createListing', $copy);

        if ($listing = $copy->activeListing()) {
            $this->redirectRoute('market.show', $listing, navigate: true);
        }
    }

    #[Computed]
    public function hasMarketName(): bool
    {
        return auth()->user()->collectorProfile()->exists();
    }

    public function save(): void
    {
        $this->authorize('createListing', $this->copy);

        abort_unless($this->hasMarketName, 403);

        $validated = $this->validate([
            'kind' => ['required', Rule::enum(ListingKind::class)],
            'price' => ['nullable', 'required_if:kind,sell', 'integer', 'between:1,1000000000'],
            'note' => ['nullable', 'string', 'max:500', new NoContactDetails],
        ]);

        $listing = $this->copy->createListing(
            auth()->user(),
            ListingKind::from($validated['kind']),
            $validated['price'] === null || $validated['price'] === '' ? null : (int) $validated['price'],
            trim((string) $validated['note']) ?: null,
        );

        session()->flash('status', __('Your book is on the market.'));

        $this->redirectRoute('market.show', $listing, navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('Offer this book'));
    }
};
?>

<div class="max-w-2xl">
    <a href="{{ route('books.show', $copy) }}" wire:navigate class="link text-sm">← {{ $copy->edition->title }}</a>
    <h1 class="mt-4 font-serif text-2xl font-semibold">{{ __('Offer this book') }}</h1>
    <p class="mt-2 text-sm text-ink-soft">{{ __('Other members will see this book and your profile name. They will not see your library, your other books, your notes or your email.') }}</p>

    @if (! $this->hasMarketName)
        <p class="mt-6 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
            {{ __('First choose the name other members will see.') }}
            <a href="{{ route('profile.edit') }}" wire:navigate class="font-medium underline">{{ __('Collector profile') }}</a>
        </p>
    @else
        <form wire:submit="save" class="mt-4 space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <div>
                <label for="kind" class="form-label">{{ __('Type') }}</label>
                <select wire:model.live="kind" id="kind" class="form-input">
                    @foreach (ListingKind::cases() as $listingKind)
                        <option wire:key="kind-{{ $listingKind->value }}" value="{{ $listingKind->value }}">{{ $listingKind->label() }}</option>
                    @endforeach
                </select>
                @error('kind') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            @if ($kind === 'sell')
                <div>
                    <label for="price" class="form-label">{{ __('Price, so‘m') }}</label>
                    <input wire:model="price" id="price" type="number" inputmode="numeric" min="1" class="form-input">
                    @error('price') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <label for="note" class="form-label">{{ __('Note for buyers (optional)') }}</label>
                <textarea wire:model="note" id="note" rows="3" maxlength="500" placeholder="{{ __('e.g. the condition, or which books you would swap it for') }}" class="form-input"></textarea>
                @error('note') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Put on the market') }}</button>
        </form>
    @endif
</div>
