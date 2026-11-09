@php
    use App\Support\Language;

    $authorNames = $authors->pluck('name')->join(', ');
    $title = $authorNames !== '' ? $edition->title.' — '.$authorNames : $edition->title;
    $description = collect([
        $edition->subtitle,
        $authorNames !== '' ? __('Author: :names', ['names' => $authorNames]) : null,
        $edition->publisher?->name,
        $edition->published_year,
        Language::name($edition->language),
        $edition->isbn_13 ? 'ISBN '.$edition->isbn_13 : null,
    ])->filter()->join('. ');
    $jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Book',
        'name' => $edition->title,
        'alternativeHeadline' => $edition->subtitle,
        'author' => $authors->map(fn ($person) => ['@type' => 'Person', 'name' => $person->name, 'url' => $person->publicUrl()])->all() ?: null,
        'publisher' => $edition->publisher ? ['@type' => 'Organization', 'name' => $edition->publisher->name] : null,
        'datePublished' => $edition->published_year ? (string) $edition->published_year : null,
        'numberOfPages' => $edition->page_count,
        'inLanguage' => $edition->language,
        'isbn' => $edition->isbn_13 ?? $edition->isbn_10,
        'image' => $edition->cover_image_url,
        'url' => $edition->publicUrl(),
    ], fn ($value) => $value !== null);
@endphp

<x-catalog-layout :title="$title" :description="$description" :canonical="$edition->publicUrl()" :indexable="$indexable" :json-ld="$indexable ? $jsonLd : null">
    <x-catalog-admin-switch class="mb-6" :record="$edition" type="books" />

    <article class="flex flex-col gap-6 sm:flex-row">
        <x-book-cover :edition="$edition" class="w-36 shrink-0 self-start sm:w-48" />

        <div class="min-w-0 flex-1">
            <h1 class="font-serif text-3xl font-semibold text-balance">{{ $edition->title }}</h1>
            @if ($edition->subtitle)
                <p class="mt-1 text-lg text-ink-soft">{{ $edition->subtitle }}</p>
            @endif
            @if ($authors->isNotEmpty())
                <p class="mt-2 text-lg">
                    @foreach ($authors as $person)<a href="{{ $person->publicUrl() }}" class="hover:text-accent hover:underline">{{ $person->name }}</a>@if (! $loop->last), @endif @endforeach
                </p>
            @endif

            <dl class="mt-5 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                @foreach ($edition->otherContributorsByRole() as $group)
                    <dt class="text-ink-soft">{{ $group['role']->label() }}</dt>
                    <dd>@foreach ($group['people'] as $person)<a href="{{ $person->publicUrl() }}" class="hover:text-accent hover:underline">{{ $person->name }}</a>@if (! $loop->last), @endif @endforeach</dd>
                @endforeach
                @if ($edition->publisher)
                    <dt class="text-ink-soft">{{ __('Publisher') }}</dt>
                    <dd>
                        @if ($edition->publisher->isPubliclyListed())
                            <a href="{{ $edition->publisher->publicUrl() }}" class="hover:text-accent hover:underline">{{ $edition->publisher->name }}</a>
                        @else
                            {{ $edition->publisher->name }}
                        @endif
                    </dd>
                @endif
                @if ($edition->published_year)
                    <dt class="text-ink-soft">{{ __('Year') }}</dt><dd>{{ $edition->published_year }}</dd>
                @endif
                @if ($edition->page_count)
                    <dt class="text-ink-soft">{{ __('Pages') }}</dt><dd>{{ $edition->page_count }}</dd>
                @endif
                @if ($edition->language)
                    <dt class="text-ink-soft">{{ __('Language') }}</dt><dd>{{ Language::name($edition->language) }}</dd>
                @endif
                @if ($edition->isbn_13 || $edition->isbn_10)
                    <dt class="text-ink-soft">ISBN</dt><dd>{{ $edition->isbn_13 ?? $edition->isbn_10 }}</dd>
                @endif
            </dl>

            @if ($edition->genres->isNotEmpty())
                <ul class="mt-4 flex flex-wrap gap-1.5">
                    @foreach ($edition->genres as $genre)
                        <li><a href="{{ route('catalog.genres.show', $genre) }}" class="rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-medium text-accent-dark hover:bg-accent/20">{{ $genre->name }}</a></li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-4 text-xs text-ink-soft">
                @if ($edition->isFromPublisher())
                    ✓ {{ __('Details from the publisher.') }}
                @endif
                @if ($shelfCount)
                    {{ trans_choice('On the shelves of :count Kitobiy library.|On the shelves of :count Kitobiy libraries.', $shelfCount) }}
                @endif
            </p>

            <div class="mt-6 rounded-xl border border-ink/10 bg-white p-4">
                <p class="font-medium">{{ __('Have this book at home?') }}</p>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Add it to your Kitobiy library by scanning the barcode. The details fill in by themselves.') }}</p>
                <a href="{{ auth()->check() ? route('books.create') : route('register') }}" class="btn-primary mt-3 w-auto">{{ auth()->check() ? __('Add a book') : __('Start your library') }}</a>
            </div>
        </div>
    </article>

    <x-news-list class="mt-10" :posts="$news" />

    @if ($otherEditions->isNotEmpty())
        <section class="mt-10">
            <h2 class="font-serif text-xl font-semibold">{{ __('Other editions and translations') }}</h2>
            <x-catalog-book-grid class="mt-3" :books="$otherEditions" />
        </section>
    @endif
</x-catalog-layout>
