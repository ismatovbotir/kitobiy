<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <header class="border-b border-ink/10 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 pt-4 pb-2">
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.dashboard') }}" wire:navigate><x-wordmark /></a>
                    <span class="rounded-full bg-ink px-2.5 py-0.5 text-xs font-semibold tracking-wide text-paper uppercase">{{ __('Admin') }}</span>
                </div>

                <div class="flex items-center gap-4">
                    <x-locale-switcher />
                    <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">{{ __('Back to my library') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-ink-soft hover:text-ink">{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
            <x-admin-nav class="mx-auto max-w-6xl px-4" />
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            {{ $slot }}
        </main>
    </body>
</html>
