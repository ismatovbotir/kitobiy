@props(['template', 'zoom' => 1, 'copy' => null])

@php
    use App\Enums\LabelElement;
    use App\Support\QrCode;
@endphp

{{-- A label drawn at real size in mm (then zoomed): with a copy's data, or with sample content. --}}
<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-sm bg-white text-ink shadow-sm ring-1 ring-ink/15 print:rounded-none print:shadow-none print:ring-0']) }}
     style="width: {{ $template->width_mm }}mm; height: {{ $template->height_mm }}mm; zoom: {{ $zoom }};"
     role="img" aria-label="{{ $copy?->edition->title ?? $template->name }}">
    @foreach ($template->elements as $element)
        @php($type = LabelElement::tryFrom($element['type']))
        @continue($type === null)
        <div wire:key="label-{{ $template->id }}-{{ $copy?->id }}-el-{{ $loop->index }}"
             class="absolute overflow-hidden leading-tight"
             style="left: {{ $element['x'] }}mm; top: {{ $element['y'] }}mm; width: {{ $element['w'] }}mm; height: {{ $element['h'] }}mm; font-size: {{ $element['font_size'] }}pt; text-align: {{ $element['align'] }}; font-weight: {{ $element['bold'] ? 700 : 400 }};">
            @if ($type === LabelElement::QrCode)
                @if ($copy)
                    <div class="size-full [&>svg]:block [&>svg]:size-full" data-qr="{{ $copy->foundPageUrl() }}">{!! QrCode::svg($copy->foundPageUrl()) !!}</div>
                @else
                    <x-label-qr />
                @endif
            @elseif ($type === LabelElement::Wordmark)
                <span class="font-serif font-semibold">{{ config('app.name') }}</span>
            @elseif ($type === LabelElement::Text)
                <span>{{ $element['text'] }}</span>
            @else
                <span @class(['font-serif' => $type === LabelElement::Title])>{{ $copy ? $copy->labelValue($type) : $type->sample() }}</span>
            @endif
        </div>
    @endforeach
</div>
