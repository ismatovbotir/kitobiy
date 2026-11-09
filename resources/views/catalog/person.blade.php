@php
    $years = $author->birth_year || $author->death_year
        ? ($author->birth_year ?? '?').'–'.($author->death_year ?? '')
        : null;
    $description = __('Books by :name in the Kitobiy catalog', ['name' => $author->name]).($years ? ' ('.$years.')' : '').'.';
@endphp

<x-catalog-layout :title="$author->name" :description="$description" :canonical="$author->publicUrl()" :indexable="$indexable"
                  :json-ld="$indexable ? ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $author->name, 'url' => $author->publicUrl()] : null">
    <x-catalog-admin-switch class="mb-6" :record="$author" type="people" />

    <h1 class="font-serif text-3xl font-semibold">{{ $author->name }}</h1>
    @if ($years || $author->country)
        <p class="mt-1 text-ink-soft">{{ collect([$years, $author->country ? \App\Support\Country::name($author->country) : null])->filter()->join(' · ') }}</p>
    @endif
    @if ($author->aliases->isNotEmpty())
        <p class="mt-1 text-sm text-ink-soft">{{ __('Also written as: :names', ['names' => $author->aliases->pluck('name')->join(', ')]) }}</p>
    @endif

    <h2 class="mt-8 font-serif text-xl font-semibold">{{ __('Books') }}</h2>
    <x-catalog-book-grid class="mt-3" :books="$books" />
</x-catalog-layout>
