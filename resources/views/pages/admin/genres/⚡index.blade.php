<?php

use App\Models\Genre;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    use RequiresSuperAdmin;

    /** The genre being edited; null = the form adds a new one. */
    #[Locked]
    public ?int $editingId = null;

    public string $slug = '';

    public string $nameUz = '';

    public string $nameRu = '';

    public string $nameEn = '';

    public bool $isActive = true;

    /**
     * @return Collection<int, Genre>
     */
    #[Computed]
    public function genres(): Collection
    {
        return Genre::query()->withCount('editions')->ordered()->get();
    }

    public function edit(int $genreId): void
    {
        $genre = Genre::findOrFail($genreId);

        $this->editingId = $genre->id;
        $this->slug = $genre->slug;
        $this->nameUz = $genre->name_uz;
        $this->nameRu = $genre->name_ru;
        $this->nameEn = $genre->name_en;
        $this->isActive = $genre->is_active;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'slug', 'nameUz', 'nameRu', 'nameEn', 'isActive');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->slug = trim($this->slug);

        $rules = [
            'nameUz' => ['required', 'string', 'max:255'],
            'nameRu' => ['required', 'string', 'max:255'],
            'nameEn' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ];

        // The slug is in filter URLs (?genre=poetry), so it is set once and never changed.
        if ($this->editingId === null) {
            $rules['slug'] = ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('genres', 'slug')];
        }

        $validated = $this->validate($rules);

        $attributes = [
            'name_uz' => trim($validated['nameUz']),
            'name_ru' => trim($validated['nameRu']),
            'name_en' => trim($validated['nameEn']),
            'is_active' => $validated['isActive'],
        ];

        if ($this->editingId === null) {
            Genre::create([...$attributes, 'slug' => $validated['slug'], 'position' => (int) Genre::max('position') + 1]);
            session()->flash('status', __('Genre added.'));
        } else {
            Genre::findOrFail($this->editingId)->update($attributes);
            session()->flash('status', __('Saved.'));
        }

        $this->cancel();
        unset($this->genres);
    }

    public function toggleActive(int $genreId): void
    {
        $genre = Genre::findOrFail($genreId);
        $genre->update(['is_active' => ! $genre->is_active]);

        unset($this->genres);
    }

    /**
     * Drag-and-drop: move a genre to a new place in the list.
     */
    public function sortGenre(int $genreId, int $position): void
    {
        $ids = Genre::query()->ordered()->pluck('id')->reject(fn (int $id): bool => $id === $genreId)->values()->all();
        array_splice($ids, max(0, min($position, count($ids))), 0, [$genreId]);

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                Genre::whereKey($id)->update(['position' => $index + 1]);
            }
        });

        unset($this->genres);
    }

    /**
     * Only a genre no edition uses can be deleted; others can be hidden instead.
     */
    public function delete(int $genreId): void
    {
        $genre = Genre::withCount('editions')->findOrFail($genreId);

        abort_if($genre->editions_count > 0, 403);

        $genre->delete();

        if ($this->editingId === $genreId) {
            $this->cancel();
        }

        unset($this->genres);
        session()->flash('status', __('Genre deleted.'));
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('Genres'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <h1 class="font-serif text-3xl font-semibold">{{ __('Genres') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('Shared genres for every book. Drag to change the order shown in pickers.') }}</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <ul wire:sort="sortGenre" class="divide-y divide-ink/10 overflow-hidden rounded-xl border border-ink/10 bg-white">
            @foreach ($this->genres as $genre)
                <li wire:key="genre-{{ $genre->id }}" wire:sort:item="{{ $genre->id }}"
                    @class(['flex items-center gap-3 px-3 py-2', 'bg-accent/5' => $editingId === $genre->id])>
                    <span wire:sort:handle class="cursor-grab px-1 text-ink-soft active:cursor-grabbing" title="{{ __('Drag to reorder') }}" aria-hidden="true">
                        <svg class="size-4" viewBox="0 0 16 16" fill="currentColor"><circle cx="5" cy="4" r="1.2"/><circle cx="11" cy="4" r="1.2"/><circle cx="5" cy="8" r="1.2"/><circle cx="11" cy="8" r="1.2"/><circle cx="5" cy="12" r="1.2"/><circle cx="11" cy="12" r="1.2"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p @class(['font-medium', 'text-ink-soft line-through' => ! $genre->is_active])>{{ $genre->name_uz }}</p>
                        <p class="truncate text-xs text-ink-soft">{{ $genre->name_ru }} · {{ $genre->name_en }} · <code>{{ $genre->slug }}</code></p>
                    </div>
                    <span class="shrink-0 text-xs text-ink-soft tabular-nums">{{ trans_choice(':count book|:count books', $genre->editions_count) }}</span>
                    <div class="flex shrink-0 gap-3 text-sm" wire:sort:ignore>
                        <button type="button" wire:click="edit({{ $genre->id }})" class="link">{{ __('Edit') }}</button>
                        <button type="button" wire:click="toggleActive({{ $genre->id }})" class="link">{{ $genre->is_active ? __('Hide') : __('Show') }}</button>
                        @if ($genre->editions_count === 0)
                            <button type="button" wire:click="delete({{ $genre->id }})" wire:confirm="{{ __('Delete this genre?') }}" class="font-medium text-red-700 hover:underline">{{ __('Delete') }}</button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <form wire:submit="save" class="h-fit space-y-4 rounded-xl border border-ink/10 bg-white p-5 lg:sticky lg:top-4">
            <h2 class="font-semibold">{{ $editingId ? __('Edit genre') : __('New genre') }}</h2>

            @if ($editingId === null)
                <div>
                    <label for="slug" class="form-label">{{ __('Code') }}</label>
                    <input id="slug" type="text" wire:model="slug" maxlength="64" placeholder="graphic_novels" class="form-input font-mono text-sm">
                    <p class="mt-1 text-xs text-ink-soft">{{ __('Latin letters, digits and _. Cannot be changed later.') }}</p>
                    @error('slug') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endif

            @foreach (['nameUz' => __('Name in Uzbek'), 'nameRu' => __('Name in Russian'), 'nameEn' => __('Name in English')] as $field => $label)
                <div wire:key="field-{{ $field }}">
                    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                    <input id="{{ $field }}" type="text" wire:model="{{ $field }}" maxlength="255" class="form-input">
                    @error($field) <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="isActive" class="size-4 accent-accent">
                {{ __('Active (can be picked)') }}
            </label>

            <div class="flex items-center gap-4">
                <button type="submit" class="btn-primary w-auto">{{ $editingId ? __('Save') : __('Add genre') }}</button>
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="text-sm text-ink-soft hover:text-ink">{{ __('Cancel') }}</button>
                @endif
            </div>
        </form>
    </div>
</div>
