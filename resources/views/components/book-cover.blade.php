@props(['edition'])

@php
    $spines = ['bg-[#8c4a2f]', 'bg-[#3f5e4a]', 'bg-[#2f4a63]', 'bg-[#7a5a2e]', 'bg-[#6b3f5a]', 'bg-[#4f4a42]'];
    $spine = $spines[crc32($edition->title) % count($spines)];
@endphp

{{-- The drawn cover is always rendered underneath; a real cover image covers it.
     Open Library answers a missing cover with a 1×1 pixel, so tiny images are dropped too. --}}
<div {{ $attributes->merge(['class' => "@container relative flex aspect-[2/3] flex-col justify-between overflow-hidden rounded p-1.5 text-white shadow-sm ring-1 ring-ink/10 {$spine}"]) }}>
    <span class="h-px w-full bg-white/40" aria-hidden="true"></span>
    <span class="hidden font-serif @[3.25rem]:line-clamp-4 text-[0.6rem] leading-tight font-semibold break-words" aria-hidden="true">{{ $edition->title }}</span>
    <span class="h-px w-full bg-white/40" aria-hidden="true"></span>

    @if ($edition->cover_image_url)
        <img src="{{ $edition->cover_image_url }}" alt="" loading="lazy"
             onerror="this.remove()" onload="if (this.naturalWidth < 10) this.remove()"
             class="absolute inset-0 size-full bg-paper object-cover">
    @endif
</div>
