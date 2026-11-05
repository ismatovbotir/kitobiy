@props(['id'])

{{--
    Comma-separated names with suggestions from the catalog.
    Bind like an input: <x-people-input id="authors" wire:model="contributors.author" />.
    The Livewire component must use App\Support\Concerns\SuggestsContributorNames.
--}}
<div
    x-data="{
        value: '',
        suggestions: [],
        open: false,
        active: 0,
        timer: null,
        get currentName() {
            return this.value.split(',').pop().trim();
        },
        typed() {
            clearTimeout(this.timer);
            const term = this.currentName;
            if (term.length < 2) {
                this.open = false;
                this.suggestions = [];
                return;
            }
            this.timer = setTimeout(async () => {
                const names = await $wire.suggestContributorNames(term);
                const already = this.value.split(',').map((name) => name.trim().toLowerCase());
                this.suggestions = names.filter((name) => ! already.includes(name.toLowerCase()) || name.toLowerCase() === term.toLowerCase());
                this.active = 0;
                this.open = this.suggestions.length > 0 && term === this.currentName;
            }, 250);
        },
        choose(name) {
            const names = this.value.split(',').map((part) => part.trim());
            names[names.length - 1] = name;
            this.value = names.filter((part) => part !== '').join(', ') + ', ';
            this.open = false;
            this.$refs.input.focus();
        },
        move(step) {
            const count = this.suggestions.length;
            if (this.open && count > 0) {
                this.active = (this.active + step + count) % count;
            }
        },
        tidy() {
            this.value = this.value.split(',').map((part) => part.trim()).filter((part) => part !== '').join(', ');
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
        maxlength="500"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-controls="{{ $id }}-suggestions"
        :aria-expanded="open"
        class="form-input"
        @input="typed()"
        @blur="setTimeout(() => { if (! open) tidy() }, 150)"
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
