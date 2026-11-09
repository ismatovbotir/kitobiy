@props(['event'])

<a href="{{ $event->publicUrl() }}" {{ $attributes->merge(['class' => 'flex h-full gap-4 rounded-xl border border-ink/10 bg-white p-4 transition hover:border-accent/40 hover:shadow-sm']) }}>
    <div @class(['flex w-14 shrink-0 flex-col items-center self-start rounded-lg py-2 text-center', 'bg-accent/10 text-accent-dark' => ! $event->isOver(), 'bg-ink/5 text-ink-soft' => $event->isOver()])>
        <span class="text-2xl font-semibold leading-none tabular-nums">{{ $event->startsAtLocal()->format('j') }}</span>
        <span class="mt-1 text-xs">{{ $event->startsAtLocal()->translatedFormat('M') }}</span>
    </div>
    <div class="min-w-0">
        <p class="font-serif text-lg font-semibold leading-snug">{{ $event->title }}</p>
        <p class="mt-1 text-sm text-ink-soft">{{ $event->dateLabel() }}</p>
        <p class="truncate text-sm text-ink-soft">{{ $event->placeLabel() }}</p>
    </div>
</a>
