{{--
    People fields of the book form. Each role is bound to a string property named
    after the role in plural (authors, translators, compilers, editors, illustrators).
--}}
@php($secondaryRoles = \App\Enums\ContributorRole::secondary())

<div class="space-y-4">
    @foreach ([\App\Enums\ContributorRole::Author, \App\Enums\ContributorRole::Translator] as $role)
        <div>
            <label for="{{ $role->value }}s" class="form-label">{{ $role->pluralLabel() }}</label>
            <x-people-input id="{{ $role->value }}s" wire:model="{{ $role->value }}s" />
            @if ($loop->first)
                <p class="mt-1 text-sm text-ink-soft">{{ __('Separate several names with commas.') }}</p>
            @endif
            @error($role->value.'s') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    @endforeach

    <details class="group rounded-lg border border-ink/10 bg-paper/50 px-3 py-2"
             @if (collect($secondaryRoles)->contains(fn ($role) => filled($this->{$role->value.'s'}))) open @endif>
        <summary class="cursor-pointer text-sm font-medium text-ink-soft">{{ __('More people: compiler, editor, illustrator') }}</summary>
        <div class="mt-3 space-y-4 pb-1">
            @foreach ($secondaryRoles as $role)
                <div>
                    <label for="{{ $role->value }}s" class="form-label">{{ $role->pluralLabel() }}</label>
                    <x-people-input id="{{ $role->value }}s" wire:model="{{ $role->value }}s" />
                    @error($role->value.'s') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </details>
</div>
