@props(['books', 'empty' => null])

@if ($books->isEmpty())
    @if ($empty)
        <p {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-ink/20 px-4 py-8 text-center text-ink-soft']) }}>{{ $empty }}</p>
    @endif
@else
    <div {{ $attributes }}>
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($books as $edition)
                <li><x-catalog-book-card :edition="$edition" /></li>
            @endforeach
        </ul>
        @if ($books instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="mt-4">{{ $books->links() }}</div>
        @endif
    </div>
@endif
