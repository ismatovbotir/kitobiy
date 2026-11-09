@php
    $location = array_filter([
        ($event->place || $event->city) ? array_filter(['@type' => 'Place', 'name' => $event->place ?? $event->city, 'address' => $event->city ? ['@type' => 'PostalAddress', 'addressLocality' => $event->city, 'addressCountry' => 'UZ'] : null]) : null,
        $event->online_url ? ['@type' => 'VirtualLocation', 'url' => $event->online_url] : null,
    ]);
    $jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event->title,
        'description' => $event->excerpt(300),
        'startDate' => $event->starts_at->timezone(config('app.timezone'))->toAtomString(),
        'endDate' => $event->ends_at?->timezone(config('app.timezone'))->toAtomString(),
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => match (true) {
            count($location) === 2 => 'https://schema.org/MixedEventAttendanceMode',
            $event->online_url !== null => 'https://schema.org/OnlineEventAttendanceMode',
            default => 'https://schema.org/OfflineEventAttendanceMode',
        },
        'location' => count($location) === 1 ? array_values($location)[0] : array_values($location),
        'image' => $event->image_url,
        'organizer' => $event->publisher ? ['@type' => 'Organization', 'name' => $event->publisher->name] : null,
        'url' => $event->publicUrl(),
    ]);
@endphp

<x-catalog-layout :title="$event->title" :description="$event->dateLabel().'. '.$event->placeLabel().'. '.$event->excerpt(100)" :canonical="$event->publicUrl()" :indexable="$event->is_published" :json-ld="$event->is_published ? $jsonLd : null">
    @can('super-admin')
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-lg border border-dashed border-accent/40 bg-white px-3 py-2 text-sm">
            <span class="font-medium text-accent">{{ __('Admin') }}</span>
            @unless ($event->is_published)
                <span class="text-ink-soft">{{ __('Draft: only super admins see this page.') }}</span>
            @endunless
            <a href="{{ route('admin.events.edit', $event) }}" class="link">{{ __('Edit') }}</a>
        </div>
    @endcan

    <article class="max-w-2xl">
        <a href="{{ route('events.index') }}" class="link text-sm">← {{ __('Book events') }}</a>
        <h1 class="mt-2 font-serif text-3xl font-semibold text-balance sm:text-4xl">{{ $event->title }}</h1>

        @if ($event->isOver())
            <p class="mt-3 inline-block rounded-full bg-ink/5 px-2.5 py-0.5 text-xs font-medium text-ink-soft">{{ __('This event is over.') }}</p>
        @endif

        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 rounded-xl border border-ink/10 bg-white p-4 text-sm">
            <dt class="text-ink-soft">{{ __('When') }}</dt>
            <dd><time datetime="{{ $event->starts_at->toAtomString() }}">{{ $event->dateLabel() }}</time></dd>
            @if ($event->place || $event->city)
                <dt class="text-ink-soft">{{ __('Where') }}</dt>
                <dd>{{ collect([$event->place, $event->city])->filter()->join(', ') }}</dd>
            @endif
            @if ($event->online_url)
                <dt class="text-ink-soft">{{ __('Online') }}</dt>
                <dd><a href="{{ $event->online_url }}" rel="nofollow noopener" target="_blank" class="link break-all">{{ parse_url($event->online_url, PHP_URL_HOST) ?: $event->online_url }}</a></dd>
            @endif
            @foreach ($related as $item)
                <dt class="text-ink-soft">{{ $item['label'] }}</dt>
                <dd><a href="{{ $item['url'] }}" class="link">{{ $item['name'] }}</a></dd>
            @endforeach
        </dl>

        @if ($event->image_url)
            <img src="{{ $event->image_url }}" alt="" class="mt-6 w-full rounded-xl bg-paper">
        @endif

        <div class="mt-6 space-y-4 leading-relaxed [&_a]:text-accent [&_a]:underline [&_h2]:font-serif [&_h2]:text-xl [&_h2]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc">
            {{ $event->bodyHtml() }}
        </div>

        @if ($event->info_url && ! $event->isOver())
            <a href="{{ $event->info_url }}" rel="nofollow noopener" target="_blank" class="btn-primary mt-6 w-auto">{{ __('Details and registration') }}</a>
        @endif
    </article>
</x-catalog-layout>
