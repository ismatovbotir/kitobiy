@props(['aliases', 'languages'])

{{-- Other spellings of a name. The Livewire component must use App\Support\Concerns\EditsAliases. --}}
<fieldset {{ $attributes }}>
    <legend class="form-label">{{ __('Other spellings') }}</legend>
    <p class="mb-2 text-sm text-ink-soft">{{ __('Cyrillic or Latin spelling, pen names. Search and suggestions match them too.') }}</p>

    <div class="space-y-2">
        @foreach ($aliases as $index => $alias)
            <div wire:key="alias-{{ $index }}" class="flex gap-2">
                <input type="text" wire:model="aliases.{{ $index }}.name" maxlength="255" class="form-input" aria-label="{{ __('Spelling') }}">
                <select wire:model="aliases.{{ $index }}.language" class="form-input w-44 shrink-0" aria-label="{{ __('Script') }}">
                    <option value="">—</option>
                    @foreach ($languages as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="removeAlias({{ $index }})" class="shrink-0 px-2 text-ink-soft hover:text-red-700" aria-label="{{ __('Remove') }}">×</button>
            </div>
            @error("aliases.{$index}.name") <p class="form-error">{{ $message }}</p> @enderror
        @endforeach
    </div>

    <button type="button" wire:click="addAlias" class="link mt-2 text-sm">+ {{ __('Add spelling') }}</button>
</fieldset>
