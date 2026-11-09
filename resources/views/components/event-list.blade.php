@props(['events'])

{{-- Upcoming events about a publisher, person or book, on its public page. --}}
@if ($events->isNotEmpty())
    <section {{ $attributes }}>
        <h2 class="font-serif text-xl font-semibold">{{ __('Upcoming events') }}</h2>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach ($events as $event)
                <li><x-event-card :event="$event" /></li>
            @endforeach
        </ul>
    </section>
@endif
