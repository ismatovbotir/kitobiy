@php
    $title = __('About Kitobiy');
    $description = __('Kitobiy began on one family’s bookshelf, with two copies of the same book. This is the story of the project and what it holds dear.');

    $values = [
        [
            'title' => __('Your shelves are your own'),
            'body' => __('A new library is private. Only people you invite or send a link to can see your books, and your notes never appear on your gift page. The public register counts all readers together, with no names and no libraries.'),
        ],
        [
            'title' => __('Every book deserves its place'),
            'body' => __('A book with no ISBN is as welcome as a new one. A worn school reader, a small local printing or a copy with a name inside the cover all belong in the catalog, and every title stays just as it was printed.'),
        ],
        [
            'title' => __('Books are meant to travel'),
            'body' => __('Books are lent, given and passed on. Kitobiy remembers who has yours, and a printed label can help a lost book find its way home.'),
        ],
        [
            'title' => __('Two alphabets, many languages'),
            'body' => __('Our shelves hold books in many languages and two alphabets. Search finds a book in Latin or Cyrillic, and these pages speak Uzbek, Russian and English.'),
        ],
    ];

    $sources = [
        1 => __('Walter Benjamin, “Unpacking My Library”, 1931.'),
        2 => __('Jorge Luis Borges, “Poem of the Gifts”, 1960.'),
    ];

    $dropCap = 'first-letter:float-left first-letter:mr-2 first-letter:font-serif first-letter:text-5xl first-letter:leading-none first-letter:text-accent';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head')
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ route('about') }}">
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ route('about') }}">
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <header class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <a href="{{ route('home') }}"><x-wordmark /></a>
            <div class="flex flex-wrap items-center gap-3">
                <x-locale-switcher />
                <a href="{{ route('catalog.index') }}" class="link text-sm">{{ __('Catalog') }}</a>
                <a href="{{ route('news.index') }}" class="link text-sm">{{ __('News') }}</a>
                <a href="{{ route('events.index') }}" class="link text-sm">{{ __('Events') }}</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="link text-sm">{{ __('Home') }}</a>
                @else
                    <a href="{{ route('login') }}" class="link text-sm">{{ __('Log in') }}</a>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 pt-6 pb-14 sm:pt-10">
            <section class="border-4 border-double border-book-dark bg-white/60 px-6 py-12 text-center sm:px-12 sm:py-16">
                <x-logo-mark class="mx-auto size-14" />
                <h1 class="mt-6 font-serif text-4xl font-semibold text-balance sm:text-5xl">{{ $title }}</h1>
                <p class="mt-3 font-serif text-lg text-balance text-ink-soft italic">{{ __('A short preface for people who love books') }}</p>
            </section>

            <figure class="mt-12 text-center">
                <blockquote class="font-serif text-2xl leading-snug text-balance italic sm:text-3xl sm:leading-snug">
                    {{ __('I am unpacking my library. Yes, I am.') }}<a href="#note-1" class="ml-1 align-super text-sm not-italic text-accent hover:underline" aria-label="{{ __('Sources') }} 1">1</a>
                </blockquote>
                <figcaption class="mt-3 text-sm text-ink-soft">{{ __('Walter Benjamin') }}</figcaption>
            </figure>

            <section class="mt-14">
                <p class="font-serif text-accent">I</p>
                <h2 class="mt-1 font-serif text-2xl font-semibold text-balance">{{ __('The shelf where it began') }}</h2>
                <p class="mt-3 leading-relaxed {{ $dropCap }}">{{ __('Kitobiy began on a shelf at home. One family of readers kept finding two copies of the same book. A home library grows quietly, one book at a time, and after a few years nobody can hold all of it in their head.') }}</p>
                <p class="mt-4 leading-relaxed">{{ __('So they began to write their books down. Then came a second thought: friends choosing a present should be able to see what is already on the shelf. And a third: other homes must have the same shelves and the same small troubles.') }}</p>
            </section>

            <section class="mt-14 border-t border-ink/10 pt-14">
                <p class="font-serif text-accent">II</p>
                <h2 class="mt-1 font-serif text-2xl font-semibold text-balance">{{ __('What the name means') }}</h2>
                <p class="mt-3 leading-relaxed {{ $dropCap }}">{{ __('Our name comes from kitob, the Uzbek word for a book, and kitobiy means “of books”. We want to be just that: a quiet helper beside your shelves. The paper, the smell of old pages and the name written inside the cover stay with you.') }}</p>
            </section>

            <section class="mt-14 border-t border-ink/10 pt-14">
                <p class="font-serif text-accent">III</p>
                <h2 class="mt-1 font-serif text-2xl font-semibold text-balance">{{ __('What we hold dear') }}</h2>
                <dl class="mt-5 flex flex-col gap-6">
                    @foreach ($values as $value)
                        <div class="border-l-4 border-book-dark pl-5">
                            <dt class="font-serif text-lg font-semibold">{{ $value['title'] }}</dt>
                            <dd class="mt-1 leading-relaxed text-ink-soft">{{ $value['body'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="mt-14 border-t border-ink/10 pt-14">
                <p class="font-serif text-accent">IV</p>
                <h2 class="mt-1 font-serif text-2xl font-semibold text-balance">{{ __('The pages still unwritten') }}</h2>
                <p class="mt-3 leading-relaxed {{ $dropCap }}">{{ __('Kitobiy is young, and this story has no last page yet. Each book a reader adds makes the catalog a little richer for the next reader, above all for Uzbek books that the big catalogs abroad do not know.') }}</p>
            </section>

            <figure class="mt-14 border-t border-ink/10 pt-14 text-center">
                <blockquote class="font-serif text-2xl leading-snug text-balance italic sm:text-3xl sm:leading-snug">
                    {{ __('I, who had always imagined Paradise as a kind of library.') }}<a href="#note-2" class="ml-1 align-super text-sm not-italic text-accent hover:underline" aria-label="{{ __('Sources') }} 2">2</a>
                </blockquote>
                <figcaption class="mt-3 text-sm text-ink-soft">{{ __('Jorge Luis Borges') }}</figcaption>
            </figure>

            @guest
                <section class="mt-14 border-4 border-double border-book-dark bg-white/60 px-6 py-10 text-center">
                    <p class="font-serif text-3xl italic">Ex libris</p>
                    <p class="mt-4 text-ink-soft">{{ __('Your shelf could be the next chapter.') }}</p>
                    <a href="{{ route('register') }}" class="btn-primary mt-6 w-auto">{{ __('Start your library') }}</a>
                </section>
            @endguest
        </main>

        <footer class="border-t border-ink/10">
            <div class="mx-auto max-w-2xl px-4 py-10">
                <h2 class="font-serif text-lg font-semibold">{{ __('Sources') }}</h2>
                <ol class="mt-3 flex flex-col gap-1 text-sm text-ink-soft">
                    @foreach ($sources as $number => $source)
                        <li id="note-{{ $number }}" class="scroll-mt-6">{{ $number }}. {{ $source }}</li>
                    @endforeach
                </ol>
                <p class="mt-3 text-sm text-ink-soft">{{ __('The translations on this page are our own.') }}</p>
            </div>
        </footer>
    </body>
</html>
