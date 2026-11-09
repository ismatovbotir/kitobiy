<?php

use App\Enums\ContributorRole;
use App\Models\Author;
use App\Support\Concerns\EditsAliases;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    use EditsAliases, RequiresSuperAdmin;

    #[Locked]
    public int $authorId;

    public string $name = '';

    public int|string|null $birthYear = null;

    public int|string|null $deathYear = null;

    public string $country = '';

    public string $wikidataId = '';

    public bool $isVerified = false;

    public string $mergeSearch = '';

    public function mount(Author $author): void
    {
        $this->authorId = $author->id;
        $this->name = $author->name;
        $this->birthYear = $author->birth_year;
        $this->deathYear = $author->death_year;
        $this->country = (string) $author->country;
        $this->wikidataId = (string) $author->wikidata_id;
        $this->isVerified = $author->is_verified;
        $this->aliases = $author->aliases()->orderBy('id')->get()
            ->map(fn ($alias): array => ['name' => $alias->name, 'language' => (string) $alias->language])
            ->all();
    }

    #[Computed]
    public function author(): Author
    {
        return Author::findOrFail($this->authorId);
    }

    /**
     * Editions this person is on, newest first, for context.
     *
     * @return Collection<int, \App\Models\Edition>
     */
    #[Computed]
    public function editions(): Collection
    {
        return $this->author->editions()->latest('editions.id')->limit(10)->get();
    }

    /**
     * Other people matching the merge search.
     *
     * @return Collection<int, Author>
     */
    #[Computed]
    public function mergeCandidates(): Collection
    {
        if (mb_strlen(trim($this->mergeSearch)) < 2) {
            return new Collection;
        }

        return Author::query()
            ->nameOrAliasContains($this->mergeSearch)
            ->whereKeyNot($this->authorId)
            ->withCount('editions')
            ->orderByDesc('is_verified')
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    public function save(): void
    {
        $this->country = mb_strtoupper(trim($this->country));
        $this->wikidataId = mb_strtoupper(trim($this->wikidataId));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'birthYear' => ['nullable', 'integer', 'between:1,'.now()->year],
            'deathYear' => ['nullable', 'integer', 'between:1,'.now()->year, 'gte:birthYear'],
            'country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'wikidataId' => ['nullable', 'regex:/^Q\d{1,12}$/', Rule::unique('authors', 'wikidata_id')->ignore($this->authorId)],
            'isVerified' => ['boolean'],
            ...$this->aliasRules(),
        ]);

        $author = $this->author;
        $author->update([
            'name' => trim($validated['name']),
            'birth_year' => $validated['birthYear'] ?: null,
            'death_year' => $validated['deathYear'] ?: null,
            'country' => $validated['country'] ?: null,
            'wikidata_id' => $validated['wikidataId'] ?: null,
            'is_verified' => $validated['isVerified'],
        ]);
        $author->syncAliases($validated['aliases'] ?? []);

        $this->mount($author->refresh());
        session()->flash('status', __('Saved.'));
    }

    /**
     * This record is a duplicate of the chosen person: move everything to them and delete this one.
     */
    public function mergeInto(int $targetId): void
    {
        $target = Author::whereKeyNot($this->authorId)->findOrFail($targetId);

        $this->author->mergeInto($target);

        session()->flash('status', __('Merged into :name.', ['name' => $target->name]));
        $this->redirectRoute('admin.authors.edit', $target, navigate: true);
    }

    public function delete(): void
    {
        abort_if($this->author->isUsed(), 403);

        $this->author->delete();

        session()->flash('status', __('Deleted.'));
        $this->redirectRoute('admin.authors.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title($this->name);
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <a href="{{ route('admin.authors.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">← {{ __('People') }}</a>
    <h1 class="mt-1 font-serif text-3xl font-semibold">{{ $this->author->name }}</h1>
    <a href="{{ $this->author->publicUrl() }}" class="link text-sm">{{ __('Public page') }}</a>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <form wire:submit="save" class="space-y-5 rounded-xl border border-ink/10 bg-white p-5">
            <div>
                <label for="name" class="form-label">{{ __('Name') }}</label>
                <input id="name" type="text" wire:model="name" maxlength="255" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="birth-year" class="form-label">{{ __('Year of birth') }}</label>
                    <input id="birth-year" type="number" wire:model="birthYear" class="form-input">
                    @error('birthYear') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="death-year" class="form-label">{{ __('Year of death') }}</label>
                    <input id="death-year" type="number" wire:model="deathYear" class="form-input">
                    @error('deathYear') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="country" class="form-label">{{ __('Country code') }}</label>
                    <input id="country" type="text" wire:model="country" maxlength="2" placeholder="UZ" class="form-input uppercase">
                    @error('country') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="wikidata" class="form-label">Wikidata ID</label>
                    <input id="wikidata" type="text" wire:model="wikidataId" maxlength="20" placeholder="Q123456" class="form-input">
                    @error('wikidataId') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <label class="mt-7 inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="isVerified" class="size-4 accent-accent">
                    {{ __('Verified') }}
                </label>
            </div>

            <x-alias-fields :aliases="$aliases" :languages="$this->aliasLanguages()" />

            <button type="submit" class="btn-primary w-auto">{{ __('Save') }}</button>
        </form>

        <aside class="space-y-6">
            <section class="rounded-xl border border-ink/10 bg-white p-5">
                <h2 class="font-semibold">{{ __('Books') }}</h2>
                @forelse ($this->editions as $edition)
                    <p wire:key="edition-{{ $edition->id }}-{{ $edition->pivot->role }}" class="mt-2 text-sm">
                        {{ $edition->title }}
                        <span class="text-ink-soft">· {{ ContributorRole::tryFrom($edition->pivot->role)?->label() }}</span>
                    </p>
                @empty
                    <p class="mt-2 text-sm text-ink-soft">{{ __('Not on any book yet.') }}</p>
                @endforelse
            </section>

            <section class="rounded-xl border border-ink/10 bg-white p-5">
                <h2 class="font-semibold">{{ __('Merge a duplicate') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('If this is the same person as another record, merge it into that one. Books and spellings move over, and this record is deleted.') }}</p>
                <input type="search" wire:model.live.debounce.300ms="mergeSearch" placeholder="{{ __('Find the other record') }}" aria-label="{{ __('Find the other record') }}" class="form-input mt-3">
                <ul class="mt-2 space-y-1">
                    @foreach ($this->mergeCandidates as $candidate)
                        <li wire:key="merge-{{ $candidate->id }}" class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 hover:bg-paper">
                            <span class="text-sm">
                                {{ $candidate->name }}
                                <span class="text-ink-soft">· {{ trans_choice(':count book|:count books', $candidate->editions_count) }}</span>
                                @if ($candidate->is_verified) <span class="text-green-700">✓</span> @endif
                            </span>
                            <button type="button" wire:click="mergeInto({{ $candidate->id }})"
                                    wire:confirm="{{ __('Merge :from into :to? This cannot be undone.', ['from' => $this->author->name, 'to' => $candidate->name]) }}"
                                    class="link shrink-0 text-sm">{{ __('Merge into') }}</button>
                        </li>
                    @endforeach
                </ul>
            </section>

            @unless ($this->author->isUsed())
                <button type="button" wire:click="delete" wire:confirm="{{ __('Delete :name?', ['name' => $this->author->name]) }}"
                        class="text-sm font-medium text-red-700 hover:underline">{{ __('Delete this record') }}</button>
            @endunless
        </aside>
    </div>
</div>
