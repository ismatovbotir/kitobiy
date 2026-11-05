@props(['copy'])

<a href="{{ route('books.show', $copy) }}" wire:navigate
   {{ $attributes->merge(['class' => 'flex h-full gap-3 rounded-xl border border-ink/10 bg-white p-3 transition hover:border-accent/40 hover:shadow-sm']) }}>
    <x-book-cover :edition="$copy->edition" class="w-14 shrink-0 self-start" />
    <div class="min-w-0">
        <p class="line-clamp-2 font-serif font-semibold leading-snug">{{ $copy->edition->title }}</p>
        @if ($copy->edition->authors->isNotEmpty())
            <p class="mt-0.5 truncate text-sm">{{ $copy->edition->authors->pluck('name')->join(', ') }}</p>
        @endif
        <p class="mt-1 truncate text-xs text-ink-soft">
            {{ collect([$copy->edition->published_year, \App\Support\Language::name($copy->edition->language)])->filter()->join(' · ') }}
        </p>
    </div>
</a>
