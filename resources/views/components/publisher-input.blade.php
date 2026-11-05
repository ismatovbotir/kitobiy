@props(['id'])

{{--
    One publisher name with suggestions from the catalog.
    Bind like an input: <x-publisher-input id="publisher" wire:model="publisher" />.
    The Livewire component must use App\Support\Concerns\SuggestsPublisherNames.
    A new name is saved as an unverified publisher, which a super admin checks later.
--}}
<div
    x-data="{
        value: '',
        suggestions: [],
        open: false,
        active: 0,
        timer: null,
        typed() {
            clearTimeout(this.timer);
            const term = this.value.trim();
            if (term.length < 2) {
                this.open = false;
                this.suggestions = [];
                return;
            }
            this.timer = setTimeout(async () => {
                const names = await $wire.suggestPublisherNames(term);
                this.suggestions = names.filter((name) => name !== this.value.trim());
                this.active = 0;
                this.open = this.suggestions.length > 0 && term === this.value.trim();
            }, 250);
        },
        choose(name) {
            this.value = name;
            this.open = false;
            this.$refs.input.focus();
        },
        move(step) {
            const count = this.suggestions.length;
            if (this.open && count > 0) {
                this.active = (this.active + step + count) % count;
            }
        },
    }"
    x-modelable="value"
    {{ $attributes->whereStartsWith('wire:model') }}
    @click.outside="open = false"
    class="relative"
>
    <input
        x-ref="input"
        x-model="value"
        id="{{ $id }}"
        type="text"
        maxlength="255"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-controls="{{ $id }}-suggestions"
        :aria-expanded="open"
        class="form-input"
        @input="typed()"
        @keydown.arrow-down.prevent="move(1)"
        @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter="if (open && suggestions[active]) { $event.preventDefault(); choose(suggestions[active]) }"
        @keydown.escape="open = false"
        @keydown.tab="open = false"
    >

    <ul
        x-show="open"
        x-cloak
        id="{{ $id }}-suggestions"
        role="listbox"
        class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-ink/15 bg-white py-1 shadow-lg"
    >
        <template x-for="(name, index) in suggestions" :key="name">
            <li
                role="option"
                :aria-selected="index === active"
                @mousedown.prevent="choose(name)"
                @mouseenter="active = index"
                :class="{ 'bg-paper': index === active }"
                class="cursor-pointer px-3 py-2"
                x-text="name"
            ></li>
        </template>
    </ul>
</div>
