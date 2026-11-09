<x-catalog-layout :title="__('Book events')" :description="__('Book fairs, author meetings and presentations in Uzbekistan and online.')" :canonical="route('events.index')">
    <h1 class="font-serif text-3xl font-semibold">{{ __('Book events') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('Book fairs, author meetings and presentations in Uzbekistan and online.') }}</p>

    <section class="mt-8">
        <h2 class="font-serif text-xl font-semibold">{{ __('Upcoming') }}</h2>
        @if ($upcoming->isEmpty())
            <p class="mt-3 rounded-xl border border-dashed border-ink/20 px-4 py-8 text-center text-ink-soft">{{ __('No events planned yet. Look in again soon.') }}</p>
        @else
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($upcoming as $event)
                    <li><x-event-card :event="$event" /></li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($past->isNotEmpty())
        <section class="mt-12">
            <h2 class="font-serif text-xl font-semibold">{{ __('Past events') }}</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($past as $event)
                    <li><x-event-card :event="$event" /></li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $past->links() }}</div>
        </section>
    @endif
</x-catalog-layout>
