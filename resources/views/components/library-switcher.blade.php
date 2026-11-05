{{-- The library the user works in, with a menu to open another one or manage them all. --}}
@php
    $user = auth()->user();
    $current = $user->currentLibrary();
    $others = $current === null ? collect() : $user->libraries()->whereKeyNot($current->id)->orderBy('name')->get();
@endphp

@if ($current)
    <details {{ $attributes->merge(['class' => 'relative']) }}>
        <summary class="flex max-w-56 cursor-pointer list-none items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-ink hover:bg-ink/5 [&::-webkit-details-marker]:hidden">
            <span class="sr-only">{{ __('Current library') }}:</span>
            <span class="truncate">{{ $current->name }}</span>
            <svg class="size-4 shrink-0 text-ink-soft" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </summary>

        <div class="absolute left-0 z-20 mt-1 w-64 rounded-lg border border-ink/10 bg-white py-1 shadow-lg">
            @foreach ($others as $library)
                <form method="POST" action="{{ route('libraries.switch', $library) }}">
                    @csrf
                    <button type="submit" class="block w-full truncate px-3 py-2 text-left text-sm hover:bg-ink/5">{{ $library->name }}</button>
                </form>
            @endforeach

            <a href="{{ route('libraries.index') }}" wire:navigate
               @class(['block px-3 py-2 text-sm text-accent hover:bg-ink/5', 'border-t border-ink/10' => $others->isNotEmpty()])>
                {{ $others->isEmpty() ? __('Add another library') : __('All my libraries') }}
            </a>
        </div>
    </details>
@endif
