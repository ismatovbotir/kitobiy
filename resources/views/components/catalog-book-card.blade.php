@props(['edition'])

{{-- A book on the public catalog pages: links to its public page, never to anyone's copy. --}}
<a href="{{ $edition->publicUrl() }}"
   {{ $attributes->merge(['class' => 'flex h-full gap-3 rounded-xl border border-ink/10 bg-white p-3 transition hover:border-accent/40 hover:shadow-sm']) }}>
    <x-book-cover :edition="$edition" class="w-14 shrink-0 self-start" />
    <div class="min-w-0">
        <p class="line-clamp-2 font-serif font-semibold leading-snug">{{ $edition->title }}</p>
        @if ($edition->authors->isNotEmpty())
            <p class="mt-0.5 truncate text-sm">{{ $edition->authors->pluck('name')->join(', ') }}</p>
        @endif
        <p class="mt-1 truncate text-xs text-ink-soft">
            {{ collect([$edition->published_year, \App\Support\Language::name($edition->language)])->filter()->join(' · ') }}
        </p>
    </div>
</a>
