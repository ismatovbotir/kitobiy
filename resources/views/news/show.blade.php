@php
    $jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $post->title,
        'datePublished' => $post->published_at?->toAtomString(),
        'dateModified' => $post->updated_at?->toAtomString(),
        'image' => $post->image_url,
        'publisher' => ['@type' => 'Organization', 'name' => config('app.name')],
        'url' => $post->publicUrl(),
    ]);
@endphp

<x-catalog-layout :title="$post->title" :description="$post->excerpt(160)" :canonical="$post->publicUrl()" :indexable="$post->isPublished()" :json-ld="$post->isPublished() ? $jsonLd : null">
    @can('super-admin')
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-lg border border-dashed border-accent/40 bg-white px-3 py-2 text-sm">
            <span class="font-medium text-accent">{{ __('Admin') }}</span>
            @unless ($post->isPublished())
                <span class="text-ink-soft">{{ $post->published_at ? __('Scheduled: only super admins see this page.') : __('Draft: only super admins see this page.') }}</span>
            @endunless
            <a href="{{ route('admin.news.edit', $post) }}" class="link">{{ __('Edit') }}</a>
        </div>
    @endcan

    <article class="max-w-2xl">
        <a href="{{ route('news.index') }}" class="link text-sm">← {{ __('News') }}</a>
        <h1 class="mt-2 font-serif text-3xl font-semibold text-balance sm:text-4xl">{{ $post->title }}</h1>
        @if ($post->published_at)
            <time datetime="{{ $post->published_at->toDateString() }}" class="mt-2 block text-sm text-ink-soft">{{ $post->published_at->timezone(config('app.timezone'))->translatedFormat('j F Y') }}</time>
        @endif

        @if ($post->image_url)
            <img src="{{ $post->image_url }}" alt="" class="mt-6 w-full rounded-xl bg-paper">
        @endif

        <div class="mt-6 space-y-4 leading-relaxed [&_a]:text-accent [&_a]:underline [&_h2]:font-serif [&_h2]:text-xl [&_h2]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc">
            {{ $post->bodyHtml() }}
        </div>

        @if ($related !== [])
            <dl class="mt-8 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 rounded-xl border border-ink/10 bg-white p-4 text-sm">
                @foreach ($related as $item)
                    <dt class="text-ink-soft">{{ $item['label'] }}</dt>
                    <dd><a href="{{ $item['url'] }}" class="link">{{ $item['name'] }}</a></dd>
                @endforeach
            </dl>
        @endif
    </article>

    @if ($morePosts->isNotEmpty())
        <section class="mt-12">
            <h2 class="font-serif text-xl font-semibold">{{ __('More news') }}</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($morePosts as $item)
                    <li><x-news-card :post="$item" /></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-catalog-layout>
