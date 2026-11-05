<?php

use App\Enums\LabelElement;
use App\Models\LabelTemplate;
use App\Support\Concerns\RequiresSuperAdmin;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    use RequiresSuperAdmin;

    #[Locked]
    public ?int $templateId = null;

    public string $name = '';

    public float|string $widthMm = 63.5;

    public float|string $heightMm = 38.1;

    public int|string $position = 0;

    public bool $isActive = true;

    /** @var list<array<string, mixed>> */
    public array $elements = [];

    public function mount(?LabelTemplate $labelTemplate = null): void
    {
        if ($labelTemplate?->exists) {
            $this->templateId = $labelTemplate->id;
            $this->name = $labelTemplate->name;
            $this->widthMm = $labelTemplate->width_mm;
            $this->heightMm = $labelTemplate->height_mm;
            $this->position = $labelTemplate->position;
            $this->isActive = $labelTemplate->is_active;
            $this->elements = $labelTemplate->elements;

            return;
        }

        $this->position = (int) LabelTemplate::max('position') + 1;
    }

    /**
     * Element types for the designer menu: name, sample text, default size.
     *
     * @return array<string, array{label: string, sample: string, square: bool, defaults: array{w: float, h: float, font_size: float}}>
     */
    public function catalog(): array
    {
        return collect(LabelElement::cases())->mapWithKeys(fn (LabelElement $element): array => [
            $element->value => [
                'label' => $element->label(),
                'sample' => $element->sample(),
                'square' => $element->isSquare(),
                'defaults' => $element->defaults(),
            ],
        ])->all();
    }

    public function save(): void
    {
        $validated = $this->validate(LabelTemplate::rules());

        $attributes = [
            'name' => trim($validated['name']),
            'width_mm' => round((float) $validated['widthMm'], 1),
            'height_mm' => round((float) $validated['heightMm'], 1),
            'position' => (int) $validated['position'],
            'is_active' => $validated['isActive'] ?? false,
        ];
        $attributes['elements'] = LabelTemplate::fitElements($validated['elements'] ?? [], $attributes['width_mm'], $attributes['height_mm']);

        $template = $this->templateId === null
            ? LabelTemplate::create($attributes)
            : tap(LabelTemplate::findOrFail($this->templateId))->update($attributes);

        session()->flash('status', __('Label template saved.'));

        $this->redirectRoute('admin.labels.edit', $template, navigate: true);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title($this->templateId ? $this->name : __('New template'));
    }
};
?>

<div x-data="labelDesigner({ elements: @js($elements), catalog: @js($this->catalog()) })"
     @keydown.window="onKey($event)"
     @pointermove.window="onPointerMove($event)"
     @pointerup.window="stopDrag()">
    <x-flash class="mb-6" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.labels.index') }}" wire:navigate class="text-sm text-ink-soft hover:text-ink">← {{ __('Label templates') }}</a>
            <h1 class="mt-1 font-serif text-3xl font-semibold">{{ $templateId ? __('Edit label template') : __('New template') }}</h1>
        </div>
        <button type="button" @click="save()" wire:loading.attr="disabled" wire:target="save" class="btn-primary w-auto">{{ __('Save template') }}</button>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200" role="alert">
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    {{-- Template settings --}}
    <div class="mt-6 grid gap-4 rounded-xl border border-ink/10 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input id="name" type="text" wire:model="name" maxlength="120" class="form-input" required>
        </div>
        <div>
            <label for="width" class="form-label">{{ __('Width (mm)') }}</label>
            <input id="width" type="number" step="0.1" min="10" max="210" wire:model="widthMm" @change="fit()" class="form-input">
        </div>
        <div>
            <label for="height" class="form-label">{{ __('Height (mm)') }}</label>
            <input id="height" type="number" step="0.1" min="10" max="210" wire:model="heightMm" @change="fit()" class="form-input">
        </div>
        <div class="flex items-end gap-4">
            <div class="w-20">
                <label for="position" class="form-label">{{ __('Order') }}</label>
                <input id="position" type="number" min="0" wire:model="position" class="form-input">
            </div>
            <label class="mb-2.5 inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="isActive" class="size-4 accent-accent">
                {{ __('Active') }}
            </label>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[12rem_1fr_16rem]">
        {{-- Element menu: drag onto the label, or click to add in the middle --}}
        <aside>
            <h2 class="text-sm font-semibold tracking-wide text-ink-soft uppercase">{{ __('Elements') }}</h2>
            <p class="mt-1 text-xs text-ink-soft">{{ __('Drag onto the label or click to add.') }}</p>
            <ul class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-1">
                @foreach (\App\Enums\LabelElement::cases() as $element)
                    <li wire:key="menu-{{ $element->value }}">
                        <button type="button" draggable="true"
                                @dragstart="$event.dataTransfer.setData('text/label-element', '{{ $element->value }}'); $event.dataTransfer.effectAllowed = 'copy'"
                                @click="add('{{ $element->value }}')"
                                class="flex w-full cursor-grab items-center gap-2 rounded-lg border border-ink/15 bg-white px-3 py-2 text-left text-sm transition hover:border-accent/50 hover:bg-accent/5 active:cursor-grabbing">
                            <svg class="size-4 shrink-0 text-ink-soft" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="5" cy="4" r="1.2"/><circle cx="11" cy="4" r="1.2"/><circle cx="5" cy="8" r="1.2"/><circle cx="11" cy="8" r="1.2"/><circle cx="5" cy="12" r="1.2"/><circle cx="11" cy="12" r="1.2"/></svg>
                            {{ $element->label() }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </aside>

        {{-- Canvas --}}
        <section class="min-w-0">
            <div class="flex items-center justify-between text-sm text-ink-soft">
                <span x-text="`${width} × ${height} mm`"></span>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" x-model="showGrid" class="size-4 accent-accent">
                    {{ __('Grid') }}
                </label>
            </div>
            <div x-ref="stage" class="mt-2 flex min-h-80 items-center justify-center overflow-hidden rounded-xl bg-ink/5 p-4"
                 @pointerdown.self="selected = null">
                <div x-ref="canvas"
                     class="relative touch-none bg-white text-ink shadow-md ring-1 ring-ink/20 select-none"
                     :style="{
                         width: `${width}mm`, height: `${height}mm`, zoom: zoom,
                         backgroundImage: showGrid ? 'linear-gradient(to right, rgb(43 38 32 / .07) 1px, transparent 1px), linear-gradient(to bottom, rgb(43 38 32 / .07) 1px, transparent 1px)' : 'none',
                         backgroundSize: '5mm 5mm',
                     }"
                     @dragover.prevent="$event.dataTransfer.dropEffect = 'copy'"
                     @drop.prevent="drop($event)"
                     @pointerdown.self="selected = null">
                    <template x-for="(element, index) in elements" :key="index">
                        <div class="absolute cursor-move overflow-visible leading-tight"
                             :style="elementStyle(element, index)"
                             :class="index === selected ? 'outline outline-[0.3mm] outline-accent' : 'outline outline-[0.2mm] outline-dashed outline-ink/25 hover:outline-accent/60'"
                             @pointerdown.prevent="startDrag($event, index, 'move')">
                            <div class="size-full overflow-hidden">
                                <template x-if="element.type === 'qr_code'">
                                    <x-label-qr />
                                </template>
                                <template x-if="element.type === 'wordmark'">
                                    <span class="font-serif font-semibold">{{ config('app.name') }}</span>
                                </template>
                                <template x-if="element.type !== 'qr_code' && element.type !== 'wordmark'">
                                    <span :class="element.type === 'title' && 'font-serif'" x-text="sampleFor(element)"></span>
                                </template>
                            </div>
                            <span x-show="index === selected"
                                  class="absolute -right-[1.2mm] -bottom-[1.2mm] size-[2.4mm] cursor-nwse-resize rounded-full border-[0.3mm] border-white bg-accent"
                                  @pointerdown.prevent.stop="startDrag($event, index, 'resize')"></span>
                        </div>
                    </template>
                </div>
            </div>
            <p class="mt-2 text-xs text-ink-soft">{{ __('Drag to move, pull the corner dot to resize. Arrow keys nudge (Shift = 2 mm), Delete removes.') }}</p>
        </section>

        {{-- Settings of the selected element --}}
        <aside>
            <h2 class="text-sm font-semibold tracking-wide text-ink-soft uppercase">{{ __('Element settings') }}</h2>
            <template x-if="current === null">
                <p class="mt-3 text-sm text-ink-soft">{{ __('Select an element on the label to change it.') }}</p>
            </template>
            <template x-if="current !== null">
                <div class="mt-3 space-y-4 rounded-xl border border-ink/10 bg-white p-4">
                    <p class="font-semibold" x-text="catalog[current.type].label"></p>

                    <template x-if="current.type === 'text'">
                        <div>
                            <label for="el-text" class="form-label">{{ __('Text') }}</label>
                            <input id="el-text" type="text" maxlength="100" x-model="current.text" class="form-input">
                        </div>
                    </template>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="el-x" class="form-label">X (mm)</label>
                            <input id="el-x" type="number" step="0.5" min="0" x-model.number="current.x" @change="fit()" class="form-input">
                        </div>
                        <div>
                            <label for="el-y" class="form-label">Y (mm)</label>
                            <input id="el-y" type="number" step="0.5" min="0" x-model.number="current.y" @change="fit()" class="form-input">
                        </div>
                        <div>
                            <label for="el-w" class="form-label">{{ __('Width') }}</label>
                            <input id="el-w" type="number" step="0.5" min="2" x-model.number="current.w" @change="fit()" class="form-input">
                        </div>
                        <div>
                            <label for="el-h" class="form-label">{{ __('Height') }}</label>
                            <input id="el-h" type="number" step="0.5" min="2" x-model.number="current.h" @change="fit()" class="form-input">
                        </div>
                    </div>

                    <template x-if="current.type !== 'qr_code'">
                        <div class="space-y-4">
                            <div>
                                <label for="el-font" class="form-label">{{ __('Font size (pt)') }}</label>
                                <input id="el-font" type="number" step="0.5" min="4" max="48" x-model.number="current.font_size" class="form-input">
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <div class="inline-flex overflow-hidden rounded-lg border border-ink/20 text-sm" role="group" aria-label="{{ __('Alignment') }}">
                                    @foreach (['left' => __('Left'), 'center' => __('Center'), 'right' => __('Right')] as $align => $alignLabel)
                                        <button type="button" @click="current.align = '{{ $align }}'"
                                                :class="current.align === '{{ $align }}' ? 'bg-ink text-paper' : 'bg-white hover:bg-ink/5'"
                                                class="px-2.5 py-1.5">{{ $alignLabel }}</button>
                                    @endforeach
                                </div>
                                <label class="inline-flex items-center gap-2 text-sm font-semibold">
                                    <input type="checkbox" x-model="current.bold" class="size-4 accent-accent">
                                    {{ __('Bold') }}
                                </label>
                            </div>
                        </div>
                    </template>

                    <div class="flex gap-4 border-t border-ink/10 pt-3 text-sm">
                        <button type="button" @click="duplicateSelected()" class="link">{{ __('Duplicate') }}</button>
                        <button type="button" @click="remove()" class="font-medium text-red-700 hover:underline">{{ __('Remove') }}</button>
                    </div>
                </div>
            </template>

            <h2 class="mt-6 text-sm font-semibold tracking-wide text-ink-soft uppercase">{{ __('On this label') }}</h2>
            <ol class="mt-2 space-y-1 text-sm">
                <template x-for="(element, index) in elements" :key="index">
                    <li>
                        <button type="button" @click="selected = index"
                                :class="index === selected ? 'bg-accent/10 font-semibold text-accent' : 'hover:bg-ink/5'"
                                class="w-full rounded-md px-2 py-1 text-left" x-text="catalog[element.type].label"></button>
                    </li>
                </template>
            </ol>
        </aside>
    </div>
</div>
