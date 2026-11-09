@props(['hint'])

{{-- The "about" fields of an admin form using App\Support\Concerns\PicksRelatedCatalogRecords. --}}
<section {{ $attributes->merge(['class' => 'space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6']) }}>
    <h2 class="font-serif text-xl font-semibold">{{ __('About') }} <span class="text-sm font-normal text-ink-soft">{{ $hint }}</span></h2>
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
