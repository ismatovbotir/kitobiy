@props(['people'])

{{-- Comma-separated names, each linking to that person's books in the library. --}}
@foreach ($people as $person)<a href="{{ route('books.index', ['person' => $person->id]) }}" wire:navigate class="hover:text-accent hover:underline">{{ $person->name }}</a>@if (! $loop->last), @endif @endforeach
