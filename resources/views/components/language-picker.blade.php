@props(['id' => 'language'])

{{--
    Searchable language picker. Bind it like an input: <x-language-picker wire:model="language" />.
    Shows the popular languages; typing at least 3 letters searches all languages
    (by name in uz/ru/en or by code).
--}}
<div
    x-data="{
        items: @js(\App\Support\Language::pickerItems()),
        value: '',
        query: '',
        open: false,
        active: 0,
        get selectedName() {
            return this.items.find((item) => item.code === this.value)?.name ?? '';
        },
        get isSearching() {
            return this.query.trim().length >= 3;
        },
        get results() {
            if (! this.isSearching) {
                return this.items.filter((item) => item.popular);
            }
            const term = this.query.trim().toLowerCase();
            return this.items.filter((item) => item.search.includes(term)).slice(0, 30);
        },
        show() {
            this.open = true;
            this.query = '';
            this.active = 0;
        },
        choose(code) {
            this.value = code;
            this.open = false;
            this.query = '';
        },
        move(step) {
            if (! this.open) {
                return this.show();
            }
            const count = this.results.length;
            if (count > 0) {
                this.active = (this.active + step + count) % count;
            }
        },
        chooseActive() {
            const item = this.results[this.active];
            if (this.open && item) {
                this.choose(item.code);
            }
        },
    }"
    x-modelable="value"
    {{ $attributes->whereStartsWith('wire:model') }}
    @click.outside="open = false"
    class="relative"
>
    <input
        id="{{ $id }}"
        type="text"
        role="combobox"
        autocomplete="off"
        aria-autocomplete="list"
        aria-controls="{{ $id }}-list"
        :aria-expanded="open"
        class="form-input"
        :placeholder="selectedName || @js(__('Not specified'))"
        :value="open ? query : selectedName"
        @focus="show()"
        @click="open || show()"
        @input="query = $event.target.value; open = true; active = 0"
        @keydown.arrow-down.prevent="move(1)"
        @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter.prevent="chooseActive()"
        @keydown.escape="open = false"
        @keydown.tab="open = false"
    >

    <div
        x-show="open"
        x-cloak
        id="{{ $id }}-list"
        role="listbox"
        class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-ink/15 bg-white py-1 shadow-lg"
    >
        <p x-show="! isSearching" class="px-3 py-1.5 text-xs text-ink-soft">{{ __('Type at least 3 letters to search all languages.') }}</p>

        <button type="button" x-show="! isSearching" @click="choose('')" class="block w-full px-3 py-2 text-left text-ink-soft hover:bg-paper">
            {{ __('Not specified') }}
        </button>

        <template x-for="(item, index) in results" :key="item.code">
            <button
                type="button"
                role="option"
                :aria-selected="item.code === value"
                @click="choose(item.code)"
                @mouseenter="active = index"
                :class="{ 'bg-paper': index === active, 'font-semibold text-accent': item.code === value }"
                class="block w-full px-3 py-2 text-left"
                x-text="item.name"
            ></button>
        </template>

        <p x-show="isSearching && results.length === 0" class="px-3 py-2 text-sm text-ink-soft">{{ __('No language found.') }}</p>
    </div>
</div>
