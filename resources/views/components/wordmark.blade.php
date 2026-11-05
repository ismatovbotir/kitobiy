<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 font-serif text-2xl font-semibold text-ink']) }}>
    <svg class="size-7 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 5.5C4.5 4.5 8.5 4.5 12 6.5c3.5-2 7.5-2 10-1V19c-2.5-1-6.5-1-10 1-3.5-2-7.5-2-10-1Z" />
        <path d="M12 6.5V20" />
    </svg>
    {{ config('app.name') }}
</span>
