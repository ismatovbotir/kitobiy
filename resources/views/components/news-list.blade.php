@props(['posts'])

{{-- News about a publisher, person or book, on its public page. --}}
@if ($posts->isNotEmpty())
    <section {{ $attributes }}>
        <h2 class="font-serif text-xl font-semibold">{{ __('News') }}</h2>
        <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <li><x-news-card :post="$post" /></li>
            @endforeach
        </ul>
    </section>
@endif
