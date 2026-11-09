@props(['title', 'description' => null, 'canonical' => null, 'indexable' => true, 'jsonLd' => null, 'search' => ''])

{{-- Layout of the public catalog pages. Only book facts appear here; see App\Support\PublicCatalog. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
        @if ($description)
            <meta name="description" content="{{ Str::limit($description, 160) }}">
        @endif
        @if ($indexable)
            @if ($canonical)
                <link rel="canonical" href="{{ $canonical }}">
            @endif
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ config('app.name') }}">
            <meta property="og:title" content="{{ $title }}">
            @if ($description)
                <meta property="og:description" content="{{ Str::limit($description, 200) }}">
            @endif
        @else
            <meta name="robots" content="noindex, follow">
        @endif
        @if ($jsonLd)
            <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endif
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <header class="border-b border-ink/10 bg-paper/90">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('home') }}"><x-wordmark /></a>
                    <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-ink-soft hover:text-ink">{{ __('Catalog') }}</a>
                    <a href="{{ route('news.index') }}" class="text-sm font-medium text-ink-soft hover:text-ink">{{ __('News') }}</a>
                    <a href="{{ route('events.index') }}" class="text-sm font-medium text-ink-soft hover:text-ink">{{ __('Events') }}</a>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-locale-switcher />
                    @auth
                        <a href="{{ route('dashboard') }}" class="link text-sm">{{ __('My library') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="link text-sm">{{ __('Log in') }}</a>
                    @endauth
                </div>
            </div>
            <form method="GET" action="{{ route('catalog.index') }}" role="search" class="mx-auto max-w-5xl px-4 pb-4">
                <label for="catalog-search" class="sr-only">{{ __('Search the catalog') }}</label>
                <input id="catalog-search" name="q" type="search" value="{{ $search }}" maxlength="100"
                       placeholder="{{ __('Title, author, publisher or ISBN') }}" class="form-input sm:max-w-md">
            </form>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            <x-flash class="mb-6" />
            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-5xl px-4 pb-10 text-center text-sm text-ink-soft">
            {{ __('Keep a list of your own books, so nobody gives you one you already have.') }}
            <a href="{{ route('register') }}" class="link">{{ __('Start your library on Kitobiy') }}</a>
        </footer>
    </body>
</html>
