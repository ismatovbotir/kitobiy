@props(['listing'])

<a href="{{ route('market.show', $listing) }}" wire:navigate
   {{ $attributes->merge(['class' => 'flex h-full gap-3 rounded-xl border border-ink/10 bg-white p-3 transition hover:border-accent/40 hover:shadow-sm']) }}>
    <x-book-cover :edition="$listing->copy->edition" class="w-14 shrink-0 self-start" />
    <div class="min-w-0">
        <p class="line-clamp-2 font-serif font-semibold leading-snug">{{ $listing->title }}</p>
        @if ($listing->copy->edition->authors->isNotEmpty())
            <p class="mt-0.5 truncate text-sm">{{ $listing->copy->edition->authors->pluck('name')->join(', ') }}</p>
        @endif
        <p class="mt-1 text-sm font-medium text-accent">{{ $listing->priceLabel() ?? $listing->kind->label() }}</p>
        <p class="mt-1 truncate text-xs text-ink-soft">{{ $listing->seller->marketName() }}</p>
    </div>
</a>
