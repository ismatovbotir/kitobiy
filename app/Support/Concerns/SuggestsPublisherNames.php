<?php

namespace App\Support\Concerns;

use App\Models\Publisher;
use App\Support\SearchKey;
use Livewire\Attributes\Renderless;

/**
 * Livewire action for <x-publisher-input>: publishers already in the catalog
 * that match what is being typed (aliases too), verified ones first, so the same
 * publisher is reused instead of being entered again with another spelling.
 */
trait SuggestsPublisherNames
{
    /**
     * @return list<string>
     */
    #[Renderless]
    public function suggestPublisherNames(string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $prefix = addcslashes(SearchKey::from($term), '%_\\').'%';

        return Publisher::query()
            ->nameOrAliasContains($term)
            ->orderByRaw('CASE WHEN search_name LIKE ? THEN 0 ELSE 1 END', [$prefix])
            ->orderByDesc('is_verified')
            ->orderBy('name')
            ->limit(8)
            ->pluck('name')
            ->all();
    }
}
