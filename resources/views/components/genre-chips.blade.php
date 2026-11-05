@props(['genres'])

{{-- Read-only genre chips for a collection of App\Models\Genre. --}}
@if (count($genres) > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5']) }}>
        @foreach ($genres as $genre)
            <span class="rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-medium text-accent-dark">{{ $genre->name }}</span>
        @endforeach
    </div>
@endif
