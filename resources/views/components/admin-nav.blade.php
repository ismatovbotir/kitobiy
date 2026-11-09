@php
    // Pages appear here once their route exists.
    $links = collect([
        ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'label' => __('Overview')],
        ['route' => 'admin.authors.index', 'active' => 'admin.authors.*', 'label' => __('People')],
        ['route' => 'admin.publishers.index', 'active' => 'admin.publishers.*', 'label' => __('Publishers')],
        ['route' => 'admin.genres.index', 'active' => 'admin.genres.*', 'label' => __('Genres')],
        ['route' => 'admin.labels.index', 'active' => 'admin.labels.*', 'label' => __('Labels')],
        ['route' => 'admin.news.index', 'active' => 'admin.news.*', 'label' => __('News')],
        ['route' => 'admin.reports.index', 'active' => 'admin.reports.*', 'label' => __('Reports')],
        ['route' => 'admin.telegram', 'active' => 'admin.telegram', 'label' => __('Telegram')],
    ])->filter(fn (array $link): bool => Route::has($link['route']));
@endphp

<nav {{ $attributes }} aria-label="{{ __('Admin') }}">
    <ul class="-mb-px flex gap-1 overflow-x-auto text-sm">
        @foreach ($links as $link)
            @php($isActive = request()->routeIs($link['active']))
            <li class="shrink-0" wire:key="admin-nav-{{ $link['route'] }}">
                <a href="{{ route($link['route']) }}" wire:navigate
                   @if ($isActive) aria-current="page" @endif
                   @class([
                       'inline-block border-b-2 px-3 py-2 transition',
                       'border-accent font-semibold text-ink' => $isActive,
                       'border-transparent text-ink-soft hover:text-ink' => ! $isActive,
                   ])>{{ $link['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>
