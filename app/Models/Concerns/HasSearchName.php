<?php

namespace App\Models\Concerns;

use App\Support\SearchKey;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Keeps `search_name` (the folded SearchKey of `name`) in step with `name`.
 * A mutator rather than a model event, so seeders without events keep it too.
 *
 * @property string $name
 * @property string $search_name
 */
trait HasSearchName
{
    /**
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn (?string $value): array => [
            'name' => $value,
            'search_name' => mb_substr(SearchKey::from($value), 0, 255),
        ]);
    }
}
