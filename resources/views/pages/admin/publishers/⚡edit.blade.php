<?php

use App\Models\Publisher;
use App\Support\Concerns\EditsAliases;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    use EditsAliases, RequiresSuperAdmin;

    /** Null while creating a new publisher. */
    #[Locked]
    public ?int $publisherId = null;

    public string $name = '';

    public string $country = '';

    public string $city = '';

    public string $website = '';

    public string $wikidataId = '';

    public bool $isVerified = true;

    public string $mergeSearch = '';

    public function mount(?Publisher $publisher = null): void
    {
        if (! $publisher?->exists) {
            return;
        }

        $this->publisherId = $publisher->id;
        $this->name = $publisher->name;
        $this->country = (string) $publisher->country;
        $this->city = (string) $publisher->city;
        $this->website = (string) $publisher->website;
        $this->wikidataId = (string) $publisher->wikidata_id;
        $this->isVerified = $publisher->is_verified;
        $this->aliases = $publisher->aliases()->orderBy('id')->get()
            ->map(fn ($alias): array => ['name' => $alias->name, 'language' => (string) $alias->language])
            ->all();
    }

    #[Computed]
    public function publisher(): ?Publisher
    {
        return $this->publisherId ? Publisher::findOrFail($this->publisherId) : null;
    }

    /**
     * @return Collection<int, \App\Models\Edition>
     */
    #[Computed]
    public function editions(): Collection
    {
        return $this->publisher?->editions()->latest('id')->limit(10)->get() ?? new Collection;
    }

    /**
     * @return Collection<int, Publisher>
     */
    #[Computed]
    public function mergeCandidates(): Collection
    {
        if ($this->publisherId === null || mb_strlen(trim($this->mergeSearch)) < 2) {
            return new Collection;
        }

        return Publisher::query()
            ->nameOrAliasContains($this->mergeSearch)
            ->whereKeyNot($this->publisherId)
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
        $this->website = trim($this->website);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'wikidataId' => ['nullable', 'regex:/^Q\d{1,12}$/', Rule::unique('publishers', 'wikidata_id')->ignore($this->publisherId)],
            'isVerified' => ['boolean'],
            ...$this->aliasRules(),
        ]);

        $attributes = [
            'name' => trim($validated['name']),
            'country' => $validated['country'] ?: null,
            'city' => trim((string) $validated['city']) ?: null,
            'website' => $validated['website'] ?: null,
            'wikidata_id' => $validated['wikidataId'] ?: null,
            'is_verified' => $validated['isVerified'],
        ];

        $publisher = $this->publisher ?? new Publisher;
        $publisher->fill($attributes)->save();
        $publisher->syncAliases($validated['aliases'] ?? []);

        session()->flash('status', __('Saved.'));
        $this->redirectRoute('admin.publishers.edit', $publisher, navigate: true);
    }

    public function mergeInto(int $targetId): void
    {
        $target = Publisher::whereKeyNot($this->publisherId)->findOrFail($targetId);

        $this->publisher->mergeInto($target);

        session()->flash('status', __('Merged into :name.', ['name' => $target->name]));
        $this->redirectRoute('admin.publishers.edit', $target, navigate: true);
    }

    public function delete(): void
    {
        abort_if($this->publisher === null || $this->publisher->isUsed(), 403);

        $this->publisher->delete();

        session()->flash('status', __('Deleted.'));
        $this->redirectRoute('admin.publishers.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title($this->publisherId ? $this->name : __('New publisher'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <a href="{{ route('admin.publishers.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">← {{ __('Publishers') }}</a>
    <h1 class="mt-1 font-serif text-3xl font-semibold">{{ $this->publisher?->name ?? __('New publisher') }}</h1>
    @if ($this->publisher)
        <a href="{{ $this->publisher->publicUrl() }}" class="link text-sm">{{ __('Public page') }}</a>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <form wire:submit="save" class="space-y-5 rounded-xl border border-ink/10 bg-white p-5">
            <div>
                <label for="name" class="form-label">{{ __('Name') }}</label>
                <input id="name" type="text" wire:model="name" maxlength="255" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="city" class="form-label">{{ __('City') }}</label>
                    <input id="city" type="text" wire:model="city" maxlength="255" class="form-input">
                    @error('city') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="country" class="form-label">{{ __('Country code') }}</label>
                    <input id="country" type="text" wire:model="country" maxlength="2" placeholder="UZ" class="form-input uppercase">
                    @error('country') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="website" class="form-label">{{ __('Website') }}</label>
                <input id="website" type="url" wire:model="website" maxlength="2048" placeholder="https://" class="form-input">
                @error('website') <p class="form-error">{{ $message }}</p> @enderror
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

        @if ($this->publisher)
            <aside class="space-y-6">
                <section class="rounded-xl border border-ink/10 bg-white p-5">
                    <h2 class="font-semibold">{{ __('Books') }}</h2>
                    @forelse ($this->editions as $edition)
                        <p wire:key="edition-{{ $edition->id }}" class="mt-2 text-sm">
                            {{ $edition->title }}
                            @if ($edition->published_year) <span class="text-ink-soft">· {{ $edition->published_year }}</span> @endif
                        </p>
                    @empty
                        <p class="mt-2 text-sm text-ink-soft">{{ __('Not on any book yet.') }}</p>
                    @endforelse
                </section>

                <section class="rounded-xl border border-ink/10 bg-white p-5">
                    <h2 class="font-semibold">{{ __('Merge a duplicate') }}</h2>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('If this is the same publisher as another record, merge it into that one. Books and spellings move over, and this record is deleted.') }}</p>
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
                                        wire:confirm="{{ __('Merge :from into :to? This cannot be undone.', ['from' => $this->publisher->name, 'to' => $candidate->name]) }}"
                                        class="link shrink-0 text-sm">{{ __('Merge into') }}</button>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @unless ($this->publisher->isUsed())
                    <button type="button" wire:click="delete" wire:confirm="{{ __('Delete :name?', ['name' => $this->publisher->name]) }}"
                            class="text-sm font-medium text-red-700 hover:underline">{{ __('Delete this record') }}</button>
                @endunless
            </aside>
        @endif
    </div>
</div>
