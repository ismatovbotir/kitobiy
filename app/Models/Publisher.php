<?php

namespace App\Models;

use App\Models\Concerns\HasNameAliases;
use App\Models\Concerns\HasSearchName;
use Database\Factories\PublisherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A publishing house. Shared reference data: imported from Wikidata (verified
 * by a super admin) or created unverified when a user types a new name.
 */
#[Fillable(['name', 'country', 'city', 'website', 'wikidata_id', 'is_verified'])]
class Publisher extends Model
{
    /** @use HasFactory<PublisherFactory> */
    use HasFactory, HasNameAliases, HasSearchName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    /**
     * The publisher with this name or alias, created unverified when there is none.
     * Returns null for a blank name.
     */
    public static function findOrCreateByName(?string $name): ?self
    {
        $name = mb_substr(trim((string) $name), 0, 255);

        if ($name === '') {
            return null;
        }

        return static::findByNameOrAlias($name) ?? static::create(['name' => $name, 'is_verified' => false]);
    }

    /**
     * @return HasMany<PublisherAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(PublisherAlias::class);
    }

    /**
     * @return HasMany<Edition, $this>
     */
    public function editions(): HasMany
    {
        return $this->hasMany(Edition::class);
    }

    /**
     * Whether any edition names this publisher.
     */
    public function isUsed(): bool
    {
        return $this->editions()->exists();
    }

    /**
     * Merge this publisher into another one (the same publisher entered twice):
     * its editions, names and missing details move to the target, then this record is deleted.
     */
    public function mergeInto(self $target): void
    {
        if ($target->is($this)) {
            throw new InvalidArgumentException('A publisher cannot be merged into itself.');
        }

        DB::transaction(function () use ($target): void {
            $this->editions()->update(['publisher_id' => $target->id]);
            $target->absorbNamesOf($this);
            $target->fillMissingFrom($this, ['country', 'city', 'website', 'wikidata_id']);
            $this->delete();
        });
    }
}
