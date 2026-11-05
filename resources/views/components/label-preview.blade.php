@props(['template', 'zoom' => 1])

@php
    use App\Enums\LabelElement;
@endphp

{{-- A label drawn at real size in mm (then zoomed), with sample content. --}}
<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-sm bg-white text-ink shadow-sm ring-1 ring-ink/15']) }}
     style="width: {{ $template->width_mm }}mm; height: {{ $template->height_mm }}mm; zoom: {{ $zoom }};"
     role="img" aria-label="{{ $template->name }}">
    @foreach ($template->elements as $element)
        @php($type = LabelElement::tryFrom($element['type']))
        @continue($type === null)
        <div wire:key="label-{{ $template->id }}-el-{{ $loop->index }}"
             class="absolute overflow-hidden leading-tight"
             style="left: {{ $element['x'] }}mm; top: {{ $element['y'] }}mm; width: {{ $element['w'] }}mm; height: {{ $element['h'] }}mm; font-size: {{ $element['font_size'] }}pt; text-align: {{ $element['align'] }}; font-weight: {{ $element['bold'] ? 700 : 400 }};">
            @if ($type === LabelElement::QrCode)
                <x-label-qr />
            @elseif ($type === LabelElement::Wordmark)
                <span class="font-serif font-semibold">{{ config('app.name') }}</span>
            @else
                <span @class(['font-serif' => $type === LabelElement::Title])>{{ $type === LabelElement::Text ? $element['text'] : $type->sample() }}</span>
            @endif
        </div>
    @endforeach
</div>
