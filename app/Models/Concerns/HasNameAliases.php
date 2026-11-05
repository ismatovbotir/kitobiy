<?php

namespace App\Models\Concerns;

use App\Support\SearchKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalog record (author, publisher) known by one main name plus aliases,
 * e.g. other spellings or scripts (Latin/Cyrillic). Lookups and searches match
 * both, through the folded `search_name` (see SearchKey and HasSearchName), so
 * apostrophes, case and Latin/Cyrillic don't matter.
 *
 * @property string $name
 */
trait HasNameAliases
{
    /**
     * @return HasMany<Model, $this>
     */
    abstract public function aliases(): HasMany;

    /**
     * The record whose name, or else one of whose aliases, is the same name
     * once folded: "Cho'lpon" finds "Choʻlpon" and "Чўлпон".
     */
    public static function findByNameOrAlias(string $name): ?static
    {
        $key = mb_substr(SearchKey::from($name), 0, 255);

        if ($key === '') {
            return null;
        }

        return static::query()->where('search_name', $key)->orderBy('id')->first()
            ?? static::query()->whereHas('aliases', fn (Builder $alias) => $alias->where('search_name', $key))->orderBy('id')->first();
    }

    /**
     * Records whose name or any alias contains the term.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function nameOrAliasContains(Builder $query, string $term): void
    {
        $pattern = SearchKey::containsPattern($term);

        if ($pattern === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(fn (Builder $inner) => $inner
            ->whereLike('search_name', $pattern)
            ->orWhereHas('aliases', fn (Builder $alias) => $alias->whereLike('search_name', $pattern)));
    }

    /**
     * Replace the aliases with the given names. Blank names, duplicates and the main name itself are skipped.
     *
     * @param  array<int, array{name: string, language?: string|null}>  $aliases
     */
    public function syncAliases(array $aliases): void
    {
        $rows = collect($aliases)
            ->map(fn (array $alias): array => [
                'name' => mb_substr(trim($alias['name']), 0, 255),
                'language' => ($alias['language'] ?? null) ?: null,
            ])
            ->filter(fn (array $alias): bool => $alias['name'] !== '' && mb_strtolower($alias['name']) !== mb_strtolower($this->name))
            ->unique(fn (array $alias): string => mb_strtolower($alias['name']))
            ->values();

        $this->aliases()->delete();
        $this->aliases()->createMany($rows->all());
        $this->unsetRelation('aliases');
    }

    /**
     * Keep the other record's main name and aliases as aliases of this one
     * (used when two records turn out to be the same person or publisher).
     */
    protected function absorbNamesOf(Model $other): void
    {
        $existing = $this->aliases()->get(['name', 'language'])
            ->map(fn (Model $alias): array => ['name' => $alias->name, 'language' => $alias->language])
            ->all();

        $incoming = $other->aliases()->get(['name', 'language'])
            ->map(fn (Model $alias): array => ['name' => $alias->name, 'language' => $alias->language])
            ->prepend(['name' => $other->name, 'language' => null])
            ->all();

        $this->syncAliases([...$existing, ...$incoming]);
    }

    /**
     * Copy the attributes this record is missing from the other one. A unique
     * wikidata_id is cleared on the other record first.
     *
     * @param  list<string>  $attributes
     */
    protected function fillMissingFrom(Model $other, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $value = $other->{$attribute};

            if (blank($this->{$attribute}) && filled($value)) {
                if ($attribute === 'wikidata_id') {
                    $other->forceFill(['wikidata_id' => null])->saveQuietly();
                }

                $this->{$attribute} = $value;
            }
        }

        $this->is_verified = $this->is_verified || $other->is_verified;
        $this->save();
    }
}
