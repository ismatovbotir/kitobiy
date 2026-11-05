<?php

namespace App\Support\Concerns;

use App\Models\Author;
use App\Support\SearchKey;
use Livewire\Attributes\Renderless;

/**
 * Livewire action for the people inputs (<x-people-input>): names already in
 * the catalog that match what is being typed, so the same person is reused
 * instead of being entered again with a different spelling. Aliases match too
 * (e.g. typing the Cyrillic spelling suggests the main name).
 */
trait SuggestsContributorNames
{
    /**
     * @return list<string>
     */
    #[Renderless]
    public function suggestContributorNames(string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $prefix = addcslashes(SearchKey::from($term), '%_\\').'%';

        return Author::query()
            ->nameOrAliasContains($term)
            ->orderByRaw('CASE WHEN search_name LIKE ? THEN 0 ELSE 1 END', [$prefix])
            ->orderBy('name')
            ->limit(8)
            ->pluck('name')
            ->all();
    }
}
