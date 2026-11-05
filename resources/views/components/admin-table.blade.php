@props(['paginator', 'empty'])

<div {{ $attributes }}>
    @if ($paginator->isEmpty())
        <p class="rounded-xl border border-ink/10 bg-white px-4 py-6 text-center text-ink-soft">{{ $empty }}</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-ink/10 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-paper text-xs tracking-wide text-ink-soft uppercase">
                    <tr>{{ $head }}</tr>
                </thead>
                <tbody>{{ $slot }}</tbody>
            </table>
        </div>
        <div class="mt-4">{{ $paginator->links() }}</div>
    @endif
</div>
