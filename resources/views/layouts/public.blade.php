<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <header class="border-b border-ink/10 bg-paper/90">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <a href="{{ route('home') }}"><x-wordmark /></a>
                <x-locale-switcher />
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-5xl px-4 pb-10 text-center text-sm text-ink-soft">
            {{ __('Keep a list of your own books, so nobody gives you one you already have.') }}
            <a href="{{ route('register') }}" class="link">{{ __('Start your library on Kitobiy') }}</a>
        </footer>
    </body>
</html>
