@props(['model' => 'genres', 'selected' => []])

{{--
    Genre chips bound to a Livewire array property of slugs:
    <x-genre-picker model="genres" :selected="$savedSlugs" />.
    Shows the active genres plus inactive ones already chosen (`selected`).
    At most Edition::MAX_GENRES can be ticked (also validated on the server).
--}}
<div x-data="{ get isFull() { return ($wire.{{ $model }} ?? []).length >= {{ \App\Models\Edition::MAX_GENRES }} } }">
    <p class="mb-2 text-sm text-ink-soft">{{ __('Choose up to :count.', ['count' => \App\Models\Edition::MAX_GENRES]) }}</p>
    <div class="flex flex-wrap gap-2">
        @foreach (\App\Models\Genre::pickable($selected) as $genre)
            <label wire:key="genre-{{ $genre->slug }}"
                   class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-ink/15 bg-white px-3 py-1 text-sm has-checked:border-accent has-checked:bg-accent/10 has-disabled:cursor-not-allowed has-disabled:opacity-50">
                <input wire:model="{{ $model }}" type="checkbox" value="{{ $genre->slug }}"
                       x-bind:disabled="isFull && ! $el.checked"
                       class="rounded border-ink/30 text-accent">
                {{ $genre->name }}
            </label>
        @endforeach
    </div>
    @error($model) <p class="form-error">{{ $message }}</p> @enderror
    @error($model.'.*') <p class="form-error">{{ $message }}</p> @enderror
</div>
