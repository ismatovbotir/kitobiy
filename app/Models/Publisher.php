<?php

namespace App\Models;

use App\Models\Concerns\AppearsInCatalog;
use App\Models\Concerns\HasNameAliases;
use App\Models\Concerns\HasSearchName;
use Database\Factories\PublisherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    use AppearsInCatalog, HasFactory, HasNameAliases, HasSearchName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'hidden_from_catalog' => 'boolean',
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

    protected function catalogRoute(): string
    {
        return 'catalog.publishers.show';
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
     * People who keep this publisher's book list, and people waiting for their
     * claim to be approved (pivot approved_at null).
     *
     * @return BelongsToMany<User, $this, PublisherMember, 'membership'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(PublisherMember::class)
            ->as('membership')
            ->withPivot('id', 'position', 'phone', 'approved_at', 'approved_by')
            ->withTimestamps();
    }

    /**
     * Whether the user is an approved member, so may keep the book list.
     */
    public function isManagedBy(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->wherePivotNotNull('approved_at')->exists();
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
            // People already with the target keep that membership; the rest move along.
            PublisherMember::query()
                ->where('publisher_id', $this->id)
                ->whereNotIn('user_id', PublisherMember::query()->where('publisher_id', $target->id)->select('user_id'))
                ->update(['publisher_id' => $target->id]);
            $target->absorbNamesOf($this);
            $target->fillMissingFrom($this, ['country', 'city', 'website', 'wikidata_id']);
            $this->delete();
        });
    }
}
