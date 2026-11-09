<x-catalog-layout :title="$genre->name" :description="__(':genre: books in the Kitobiy catalog.', ['genre' => $genre->name])" :canonical="route('catalog.genres.show', $genre)">
    <a href="{{ route('catalog.index') }}" class="link text-sm">← {{ __('Book catalog') }}</a>
    <h1 class="mt-2 font-serif text-3xl font-semibold">{{ $genre->name }}</h1>

    <x-catalog-book-grid class="mt-6" :books="$books" :empty="__('No books in this genre yet.')" />
</x-catalog-layout>
