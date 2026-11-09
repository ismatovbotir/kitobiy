@php
    $title = $search !== '' ? __('Search: :query', ['query' => $search]) : __('Book catalog');
    $description = __('Books, writers and publishers that Kitobiy readers keep on their shelves. Find a book and add it to your own library.');
@endphp

<x-catalog-layout :title="$title" :description="$description" :canonical="route('catalog.index')" :indexable="$search === ''" :search="$search">
    <h1 class="font-serif text-3xl font-semibold">{{ $title }}</h1>

    @if ($search !== '')
        @if ($people->isNotEmpty() || $publishers->isNotEmpty())
            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                @if ($people->isNotEmpty())
                    <section>
                        <h2 class="font-serif text-xl font-semibold">{{ __('People') }}</h2>
                        <ul class="mt-2 space-y-1">
                            @foreach ($people as $person)
                                <li><a href="{{ $person->publicUrl() }}" class="link">{{ $person->name }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                @if ($publishers->isNotEmpty())
                    <section>
                        <h2 class="font-serif text-xl font-semibold">{{ __('Publishers') }}</h2>
                        <ul class="mt-2 space-y-1">
                            @foreach ($publishers as $item)
                                <li><a href="{{ $item->publicUrl() }}" class="link">{{ $item->name }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        @endif

        <h2 class="mt-8 font-serif text-xl font-semibold">{{ __('Books') }}</h2>
        <x-catalog-book-grid class="mt-3" :books="$books" :empty="__('Nothing found.')" />
    @else
        <p class="mt-2 max-w-2xl text-ink-soft">{{ $description }}</p>

        <section class="mt-8">
            <h2 class="font-serif text-xl font-semibold">{{ __('Genres') }}</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($genres as $genre)
                    <li>
                        <a href="{{ route('catalog.genres.show', $genre) }}" class="inline-flex items-center gap-1.5 rounded-full border border-ink/15 bg-white px-3 py-1 text-sm hover:border-accent">
                            {{ $genre->name }} <span class="text-xs text-ink-soft tabular-nums">{{ $genre->editions_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @if ($newFromPublishers->isNotEmpty())
            <section class="mt-10">
                <h2 class="font-serif text-xl font-semibold">{{ __('New from publishers') }}</h2>
                <x-catalog-book-grid class="mt-3" :books="$newFromPublishers" />
            </section>
        @endif

        @if ($publishers->isNotEmpty())
            <section class="mt-10">
                <h2 class="font-serif text-xl font-semibold">{{ __('Publishers') }}</h2>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($publishers as $item)
                        <li>
                            <a href="{{ $item->publicUrl() }}" class="flex items-center justify-between gap-2 rounded-xl border border-ink/10 bg-white px-4 py-3 hover:border-accent/40">
                                <span class="truncate font-medium">{{ $item->name }}</span>
                                <span class="shrink-0 text-xs text-ink-soft">{{ trans_choice(':count book|:count books', $item->editions_count) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</x-catalog-layout>
