<x-catalog-layout :title="__('News')" :description="__('News from Kitobiy: publishers who joined, new features and book events.')" :canonical="route('news.index')">
    <h1 class="font-serif text-3xl font-semibold">{{ __('News') }}</h1>

    @if ($posts->isEmpty())
        <p class="mt-6 rounded-xl border border-dashed border-ink/20 px-4 py-8 text-center text-ink-soft">{{ __('No news yet.') }}</p>
    @else
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <li><x-news-card :post="$post" /></li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $posts->links() }}</div>
    @endif
</x-catalog-layout>
