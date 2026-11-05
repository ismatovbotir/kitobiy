<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <header class="border-b border-ink/10 bg-paper/90">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 pt-4 pb-2">
                <div class="flex min-w-0 items-center gap-2">
                    <a href="{{ route('dashboard') }}" wire:navigate><x-wordmark /></a>
                    @if (auth()->user()->hasVerifiedEmail())
                        <x-library-switcher />
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    <x-locale-switcher />
                    @can('super-admin')
                        <a href="{{ route('admin.dashboard') }}" wire:navigate class="text-sm font-medium text-accent hover:text-accent-dark">{{ __('Admin') }}</a>
                    @endcan
                    <span class="hidden text-sm text-ink-soft sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-ink-soft hover:text-ink">{{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
            @if (auth()->user()->libraries()->exists())
                <x-main-nav class="mx-auto max-w-5xl px-4" />
            @else
                <div class="h-2"></div>
            @endif
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            {{ $slot }}
        </main>
    </body>
</html>
