@props(['post'])

<a href="{{ $post->publicUrl() }}" {{ $attributes->merge(['class' => 'flex h-full flex-col overflow-hidden rounded-xl border border-ink/10 bg-white transition hover:border-accent/40 hover:shadow-sm']) }}>
    @if ($post->image_url)
        <img src="{{ $post->image_url }}" alt="" loading="lazy" class="aspect-[3/2] w-full bg-paper object-cover">
    @endif
    <div class="flex flex-1 flex-col p-4">
        <time datetime="{{ $post->published_at?->toDateString() }}" class="text-xs text-ink-soft">{{ $post->published_at?->timezone(config('app.timezone'))->translatedFormat('j F Y') }}</time>
        <p class="mt-1 font-serif text-lg font-semibold leading-snug">{{ $post->title }}</p>
        <p class="mt-2 line-clamp-3 text-sm text-ink-soft">{{ $post->excerpt() }}</p>
    </div>
</a>
