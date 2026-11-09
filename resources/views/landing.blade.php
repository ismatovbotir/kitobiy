@php
    $title = __('Your home library, in order');
    $description = __('Kitobiy is a catalog for the books you own. Know what is on your shelves, who has borrowed what, and which book not to buy twice.');

    $chapters = [
        [
            'id' => 'catalog',
            'numeral' => 'I',
            'title' => __('Every book, one list'),
            'quote' => __('Some books are to be tasted, others to be swallowed, and some few to be chewed and digested.'),
            'author' => __('Francis Bacon'),
            'note' => 2,
            'body' => __('Scan the barcode or type the ISBN, and Kitobiy looks the book up for you. Old editions with no ISBN go in by hand. Add genres, tags and private notes, then find any book by title, author or ISBN, in Latin or Cyrillic.'),
        ],
        [
            'id' => 'gifts',
            'numeral' => 'II',
            'title' => __('Never the same book twice'),
            'quote' => __('A multitude of books distracts.'),
            'author' => __('Seneca'),
            'note' => 3,
            'body' => __('Kitobiy shows the books you have twice, even in different editions. Send friends the link to your gift page: they see what you have and what you wish for, so nobody gives you a book already on your shelf. Your notes and your members stay hidden.'),
        ],
        [
            'id' => 'family',
            'numeral' => 'III',
            'title' => __('A library the whole family shares'),
            'quote' => __('Reading good books is like a conversation with the finest people of past centuries.'),
            'author' => __('René Descartes'),
            'note' => 4,
            'body' => __('Invite your family with a link. Everyone adds books to one catalog, and you choose who can edit and who can only look. Open another library for a second home or a separate collection.'),
        ],
        [
            'id' => 'way-home',
            'numeral' => 'IV',
            'title' => __('A book that finds its way home'),
            'quote' => __('The best companion in all of time is a book.'),
            'author' => __('Al-Mutanabbi'),
            'note' => 5,
            'body' => __('Write down who borrowed each book, and mark it when it comes back. Print QR labels on ordinary A4 sticker sheets. Whoever finds a lost book scans the label and sends you a message, without seeing your address or phone number.'),
        ],
    ];

    $sources = [
        1 => __('Cicero, Letters to Atticus, IV.8. Tyrannio was the scholar who put Cicero’s library in order.'),
        2 => __('Francis Bacon, “Of Studies”, Essays, 1625.'),
        3 => __('Seneca, Letters to Lucilius, letter 2.'),
        4 => __('René Descartes, Discourse on the Method, Part I, 1637.'),
        5 => __('Al-Mutanabbi, Arabic poet of the 10th century.'),
    ];

    // Book titles are never translated. Later spines drop away on narrow screens.
    $spines = [
        ['Xamsa', 'h-44', 'bg-book-dark text-paper'],
        ['O‘tkan kunlar', 'h-52', 'bg-accent text-white'],
        ['Kecha va kunduz', 'h-48', 'bg-book-light text-ink'],
        ['Boburnoma', 'h-40', 'bg-ink text-paper'],
        ['Mehrobdan chayon', 'h-52', 'bg-book-dark text-paper'],
        ['Qutadg‘u bilig', 'h-44', 'bg-book-light text-ink'],
        ['Sarob', 'h-36 max-sm:hidden', 'bg-accent text-white'],
        ['Shum bola', 'h-44 max-sm:hidden', 'bg-ink text-paper'],
        ['Alpomish', 'h-48 max-sm:hidden', 'bg-book-dark text-paper'],
        ['Ulug‘bek xazinasi', 'h-52 max-md:hidden', 'bg-book-light text-ink'],
        ['Devonu lug‘otit turk', 'h-52 max-md:hidden', 'bg-accent text-white'],
        ['Yulduzli tunlar', 'h-48 max-lg:hidden', 'bg-ink text-paper'],
        ['Ikki eshik orasi', 'h-44 max-lg:hidden', 'bg-book-dark text-paper'],
        ['Sinchalak', 'h-36 max-lg:hidden', 'bg-book-light text-ink'],
    ];

    $drawers = [
        __('Readers') => $stats['readers'],
        __('Home libraries') => $stats['libraries'],
        __('Books on shelves') => $stats['books'],
        __('Different editions') => $stats['editions'],
    ];

    $regionNames = App\Support\Country::regionOptions(App\Support\Country::UZBEKISTAN);

    $formatCount = fn (int $count): string => number_format($count, 0, '', "\u{00A0}");
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head')
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ route('home') }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ route('home') }}">
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <header class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <a href="{{ route('home') }}"><x-wordmark /></a>
            <div class="flex flex-wrap items-center gap-3">
                <x-locale-switcher />
                <a href="{{ route('catalog.index') }}" class="link text-sm">{{ __('Catalog') }}</a>
                <a href="{{ route('news.index') }}" class="link text-sm">{{ __('News') }}</a>
                <a href="{{ route('about') }}" class="link text-sm">{{ __('About the project') }}</a>
                <a href="{{ route('login') }}" class="link text-sm">{{ __('Log in') }}</a>
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-6xl gap-10 px-4 pt-10 pb-12 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:gap-16 lg:pt-16">
                <div class="min-w-0">
                    <figure>
                        <blockquote class="font-serif text-3xl leading-tight text-balance italic sm:text-5xl sm:leading-tight">
                            {{ __('Now that Tyrannio has arranged my books, my house seems to have gained a mind.') }}<a href="#note-1" class="ml-1 align-super text-sm not-italic text-accent hover:underline" aria-label="{{ __('Sources') }} 1">1</a>
                        </blockquote>
                        <figcaption class="mt-4 text-sm text-ink-soft">{{ __('Cicero, in a letter to his friend Atticus') }}</figcaption>
                    </figure>

                    <h1 class="mt-10 font-serif text-2xl font-semibold text-balance sm:text-3xl">{{ $title }}</h1>
                    <p class="mt-3 max-w-xl text-lg leading-relaxed text-ink-soft">{{ $description }}</p>
                    <a href="{{ route('register') }}" class="btn-primary mt-6 w-auto">{{ __('Start your library') }}</a>
                </div>

                <nav aria-labelledby="contents-title" class="self-end">
                    <h2 id="contents-title" class="font-serif text-lg font-semibold">{{ __('Contents') }}</h2>
                    <ol class="mt-3 flex flex-col gap-1 text-sm">
                        @foreach ($chapters as $chapter)
                            <li>
                                <a href="#{{ $chapter['id'] }}" class="group flex min-h-11 items-baseline gap-2 py-2">
                                    <span class="group-hover:text-accent">{{ $chapter['title'] }}</span>
                                    <span class="min-w-4 grow border-b border-dotted border-ink/40" aria-hidden="true"></span>
                                    <span class="font-serif text-ink-soft">{{ $chapter['numeral'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            </section>

            <div class="mx-auto max-w-6xl px-4" aria-hidden="true">
                <div class="flex h-56 items-end gap-1 border-b-8 border-book-dark">
                    @foreach ($spines as [$spineTitle, $spineSize, $spineColor])
                        <div @class(['flex min-w-0 flex-1 items-center justify-center overflow-hidden rounded-t-sm px-1 py-3 font-serif text-sm [text-orientation:sideways] [writing-mode:vertical-rl] motion-safe:transition-transform motion-safe:hover:-translate-y-2', $spineSize, $spineColor])>
                            <span class="truncate">{{ $spineTitle }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($stats['isReady'])
                <section id="register" aria-labelledby="register-title" class="mx-auto max-w-6xl px-4 pt-14 pb-4 md:pt-20">
                    <h2 id="register-title" class="font-serif text-2xl font-semibold text-balance sm:text-3xl">{{ __('The library register') }}</h2>
                    <p class="mt-2 max-w-2xl leading-relaxed text-ink-soft">{{ __('All Kitobiy readers, counted together. No names and no libraries are shown.') }}</p>

                    <dl class="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        @foreach ($drawers as $label => $count)
                            <div class="flex min-w-0 flex-col-reverse items-center gap-3 rounded-sm border border-book-dark/30 bg-white/60 px-3 pt-4 pb-3 text-center">
                                <span class="size-2.5 rounded-full bg-book-dark/60" aria-hidden="true"></span>
                                <dd class="font-serif text-3xl font-semibold text-accent sm:text-4xl">{{ $formatCount($count) }}</dd>
                                <dt class="w-full border border-book-dark/30 px-2 py-1 text-sm">{{ $label }}</dt>
                            </div>
                        @endforeach
                    </dl>

                    @if ($stats['mostShelved'] !== [] || $stats['regions'] !== [])
                        <div class="mt-10 grid gap-10 md:grid-cols-2 md:gap-16">
                            @if ($stats['mostShelved'] !== [])
                                <div class="min-w-0">
                                    <h3 class="font-serif text-xl font-semibold">{{ __('Most often on the shelves') }}</h3>
                                    <p class="mt-1 text-sm text-ink-soft">{{ __('The number shows how many readers have the book.') }}</p>
                                    <ol class="mt-3 flex flex-col gap-1">
                                        @foreach ($stats['mostShelved'] as $book)
                                            <li class="flex items-baseline gap-2 py-1">
                                                <span class="min-w-0">
                                                    <span class="font-serif">{{ $book['title'] }}</span>
                                                    @if ($book['authors'] !== '')
                                                        <span class="block text-sm text-ink-soft">{{ $book['authors'] }}</span>
                                                    @endif
                                                </span>
                                                <span class="min-w-4 grow border-b border-dotted border-ink/40" aria-hidden="true"></span>
                                                <span class="font-serif text-ink-soft">{{ $formatCount($book['readers']) }}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            @endif

                            @if ($stats['regions'] !== [])
                                <div class="min-w-0">
                                    <h3 class="font-serif text-xl font-semibold">{{ __('Where our readers are') }}</h3>
                                    <p class="mt-1 text-sm text-ink-soft">{{ __('The number shows how many readers live in the region.') }}</p>
                                    <ol class="mt-3 flex flex-col gap-1">
                                        @foreach ($stats['regions'] as $region)
                                            <li class="flex items-baseline gap-2 py-1">
                                                <span class="min-w-0 font-serif">{{ $regionNames[$region['code']] }}</span>
                                                <span class="min-w-4 grow border-b border-dotted border-ink/40" aria-hidden="true"></span>
                                                <span class="font-serif text-ink-soft">{{ $formatCount($region['readers']) }}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3">
                        <a href="{{ route('register') }}" class="btn-primary w-auto">{{ __('Start your library') }}</a>
                        <p class="font-serif text-lg italic">{{ __('Your shelves are not counted yet.') }}</p>
                    </div>
                    <p class="mt-4 max-w-2xl text-sm text-ink-soft">{{ __('A book or a region is named only when at least :count readers share it. Updated every hour.', ['count' => App\Support\CommunityStats::MIN_READERS_PER_ROW]) }}</p>
                </section>
            @else
                <section id="register" aria-labelledby="register-title" class="mx-auto max-w-6xl px-4 pt-14 pb-4 md:pt-20">
                    <div class="max-w-2xl border-l-4 border-book-dark pl-5">
                        <h2 id="register-title" class="font-serif text-2xl font-semibold text-balance sm:text-3xl">{{ __('The first page of the register') }}</h2>
                        <p class="mt-3 leading-relaxed">{{ __('Kitobiy has just opened. Soon this page will show how many books our readers hold, which books they own most often and where they live. The first readers are writing the first page: come and be one of them.') }}</p>
                        <a href="{{ route('register') }}" class="btn-primary mt-6 w-auto">{{ __('Start your library') }}</a>
                    </div>
                </section>
            @endif

            @foreach ($chapters as $chapter)
                <section id="{{ $chapter['id'] }}" @class(['mx-auto grid max-w-6xl scroll-mt-6 gap-8 px-4 py-14 md:grid-cols-[minmax(0,5fr)_minmax(0,4fr)] md:gap-16 md:py-20', 'border-t border-ink/10' => ! $loop->first])>
                    <figure class="min-w-0">
                        <blockquote class="font-serif text-2xl leading-snug text-balance italic sm:text-4xl sm:leading-snug">
                            {{ $chapter['quote'] }}<a href="#note-{{ $chapter['note'] }}" class="ml-1 align-super text-sm not-italic text-accent hover:underline" aria-label="{{ __('Sources') }} {{ $chapter['note'] }}">{{ $chapter['note'] }}</a>
                        </blockquote>
                        <figcaption class="mt-3 text-sm text-ink-soft">{{ $chapter['author'] }}</figcaption>
                    </figure>
                    <div class="min-w-0">
                        <p class="font-serif text-accent">{{ $chapter['numeral'] }}</p>
                        <h2 class="mt-1 font-serif text-2xl font-semibold text-balance">{{ $chapter['title'] }}</h2>
                        <p class="mt-3 leading-relaxed first-letter:float-left first-letter:mr-2 first-letter:font-serif first-letter:text-5xl first-letter:leading-none first-letter:text-accent">{{ $chapter['body'] }}</p>
                    </div>
                </section>
            @endforeach

            <section class="px-4 py-14">
                <div class="mx-auto max-w-xl border-4 border-double border-book-dark bg-white/60 px-6 py-10 text-center">
                    <x-logo-mark class="mx-auto size-14" />
                    <p class="mt-4 font-serif text-3xl italic">Ex libris</p>
                    <p class="mt-4 text-sm text-ink-soft">{{ __('This library belongs to') }}</p>
                    <p class="mx-auto mt-6 max-w-xs border-b border-dotted border-ink/50" aria-hidden="true"></p>
                    <a href="{{ route('register') }}" class="btn-primary mt-8 w-auto">{{ __('Start your library') }}</a>
                    <p class="mt-4 text-sm text-ink-soft">{{ __('Private from day one. Only people you invite or send a link to can see your books.') }}</p>
                </div>
            </section>
        </main>

        <footer class="border-t border-ink/10">
            <div class="mx-auto max-w-6xl px-4 py-10">
                <h2 class="font-serif text-lg font-semibold">{{ __('Sources') }}</h2>
                <ol class="mt-3 flex max-w-2xl flex-col gap-1 text-sm text-ink-soft">
                    @foreach ($sources as $number => $source)
                        <li id="note-{{ $number }}" class="scroll-mt-6">{{ $number }}. {{ $source }}</li>
                    @endforeach
                </ol>
                <p class="mt-3 text-sm text-ink-soft">{{ __('The translations on this page are our own.') }}</p>
                <a href="{{ route('about') }}" class="link mt-6 inline-block text-sm">{{ __('About the project') }}</a>
            </div>
        </footer>
    </body>
</html>
