<?php

namespace App\Support\Concerns;

use Illuminate\Validation\Rule;

/**
 * For admin edit pages of records with name aliases (people, publishers):
 * an editable list of other spellings, bound to <x-alias-fields>.
 */
trait EditsAliases
{
    /** @var list<array{name: string, language: string}> */
    public array $aliases = [];

    public function addAlias(): void
    {
        $this->aliases[] = ['name' => '', 'language' => ''];
    }

    public function removeAlias(int $index): void
    {
        unset($this->aliases[$index]);
        $this->aliases = array_values($this->aliases);
    }

    /**
     * Writing systems an alias can be tagged with.
     *
     * @return array<string, string>
     */
    public function aliasLanguages(): array
    {
        return [
            'uz-Latn' => __('Uzbek (Latin)'),
            'uz-Cyrl' => __('Uzbek (Cyrillic)'),
            'ru' => __('Russian'),
            'en' => __('English'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function aliasRules(): array
    {
        return [
            'aliases' => ['array', 'max:30'],
            'aliases.*.name' => ['nullable', 'string', 'max:255'],
            'aliases.*.language' => ['nullable', Rule::in(array_keys($this->aliasLanguages()))],
        ];
    }
}
