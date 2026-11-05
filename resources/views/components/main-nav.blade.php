@php
    $canAddBooks = auth()->user()->can('addBooks', auth()->user()->currentLibrary());
    $links = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'label' => __('Home')],
        ['route' => 'books.index', 'active' => ['books.index', 'books.show'], 'label' => __('Books')],
        ['route' => 'books.create', 'active' => 'books.create', 'label' => __('Add a book'), 'show' => $canAddBooks],
        ['route' => 'books.duplicates', 'active' => 'books.duplicates', 'label' => __('Duplicates')],
        ['route' => 'library.wishlist', 'active' => 'library.wishlist', 'label' => __('Wishlist')],
        ['route' => 'library.members', 'active' => 'library.members', 'label' => __('Family')],
        ['route' => 'market.index', 'active' => ['market.index', 'market.show', 'market.create'], 'label' => __('Market')],
        ['route' => 'messages.index', 'active' => ['messages.index', 'messages.show'], 'label' => __('Messages'), 'badge' => auth()->user()->unreadMessagesCount()],
        ['route' => 'profile.edit', 'active' => 'profile.edit', 'label' => __('Profile')],
    ];
    $links = array_filter($links, fn (array $link): bool => $link['show'] ?? true);
@endphp

<nav {{ $attributes }} aria-label="{{ __('Main') }}">
    <ul class="-mb-px flex gap-1 overflow-x-auto text-sm">
        @foreach ($links as $link)
            @php($isActive = request()->routeIs($link['active']))
            <li class="shrink-0">
                <a href="{{ route($link['route']) }}" wire:navigate
                   @if ($isActive) aria-current="page" @endif
                   @class([
                       'inline-block border-b-2 px-3 py-2 transition',
                       'border-accent font-semibold text-ink' => $isActive,
                       'border-transparent text-ink-soft hover:text-ink' => ! $isActive,
                   ])>{{ $link['label'] }}@if ($link['badge'] ?? 0) <span class="ml-1 rounded-full bg-accent px-1.5 text-xs font-semibold text-white">{{ $link['badge'] }}</span>@endif</a>
            </li>
        @endforeach
    </ul>
</nav>
