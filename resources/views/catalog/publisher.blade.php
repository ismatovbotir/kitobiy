@php
    $description = __('Books of :name in the Kitobiy catalog.', ['name' => $publisher->name]);
@endphp

<x-catalog-layout :title="$publisher->name" :description="$description" :canonical="$publisher->publicUrl()" :indexable="$indexable"
                  :json-ld="$indexable ? array_filter(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $publisher->name, 'url' => $publisher->publicUrl(), 'sameAs' => $publisher->website]) : null">
    <x-catalog-admin-switch class="mb-6" :record="$publisher" type="publishers" />

    <h1 class="font-serif text-3xl font-semibold">{{ $publisher->name }}</h1>
    <p class="mt-1 text-ink-soft">
        {{ collect([$publisher->city, $publisher->country ? \App\Support\Country::name($publisher->country) : null])->filter()->join(', ') }}
        @if ($publisher->website)
            · <a href="{{ $publisher->website }}" rel="nofollow noopener" target="_blank" class="link">{{ parse_url($publisher->website, PHP_URL_HOST) ?: $publisher->website }}</a>
        @endif
    </p>
    @if ($keepsOwnList)
        <p class="mt-2 text-sm text-ink-soft">✓ {{ __('The publisher keeps this book list itself.') }}</p>
    @endif

    <x-event-list class="mt-10" :events="$events" />
    <x-news-list class="mt-10" :posts="$news" />

    <h2 class="mt-8 font-serif text-xl font-semibold">{{ __('Books') }}</h2>
    <x-catalog-book-grid class="mt-3" :books="$books" />

    @unless ($keepsOwnList)
        <p class="mt-8 text-sm text-ink-soft">
            {{ __('Work at this publisher?') }}
            <a href="{{ route('publisher.index') }}" class="link">{{ __('Add your books to Kitobiy') }}</a>
        </p>
    @endunless
</x-catalog-layout>
