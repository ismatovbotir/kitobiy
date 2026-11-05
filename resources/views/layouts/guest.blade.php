<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <div class="mx-auto flex min-h-screen max-w-md flex-col px-4 py-8">
            <header class="flex items-center justify-between">
                <a href="{{ route('home') }}"><x-wordmark /></a>
                <x-locale-switcher />
            </header>

            <main class="mt-10 flex-1">
                <div class="rounded-2xl border border-ink/10 bg-white/70 p-6 shadow-sm sm:p-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
