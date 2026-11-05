<?php

namespace App\Models;

use App\Models\Concerns\HasNameAliases;
use App\Models\Concerns\HasSearchName;
use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A person who contributed to editions: author, translator, compiler, editor or illustrator.
 * Imported from Wikidata (verified) or created unverified when a user types a new name.
 */
#[Fillable(['name', 'birth_year', 'death_year', 'country', 'wikidata_id', 'is_verified'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory, HasNameAliases, HasSearchName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'death_year' => 'integer',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * The person with this name or alias, created unverified when there is none.
     */
    public static function findOrCreateByName(string $name): self
    {
        return static::findByNameOrAlias($name) ?? static::create(['name' => trim($name), 'is_verified' => false]);
    }

    /**
     * @return HasMany<AuthorAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(AuthorAlias::class);
    }

    /**
     * Editions this person contributed to, in any role.
     *
     * @return BelongsToMany<Edition, $this>
     */
    public function editions(): BelongsToMany
    {
        return $this->belongsToMany(Edition::class)->withPivot('role', 'position');
    }

    /**
     * Whether any edition names this person (in any role).
     */
    public function isUsed(): bool
    {
        return $this->editions()->exists();
    }

    /**
     * Merge this person into another one (the same person entered twice):
     * their editions, names and missing details move to the target, then this record is deleted.
     */
    public function mergeInto(self $target): void
    {
        if ($target->is($this)) {
            throw new InvalidArgumentException('A person cannot be merged into themselves.');
        }

        DB::transaction(function () use ($target): void {
            $rows = DB::table('author_edition')->where('author_id', $this->id)->get();

            foreach ($rows as $row) {
                $targetHasRole = DB::table('author_edition')
                    ->where(['edition_id' => $row->edition_id, 'author_id' => $target->id, 'role' => $row->role])
                    ->exists();

                if (! $targetHasRole) {
                    DB::table('author_edition')
                        ->where(['edition_id' => $row->edition_id, 'author_id' => $this->id, 'role' => $row->role])
                        ->update(['author_id' => $target->id]);
                }
            }

            $target->absorbNamesOf($this);
            $target->fillMissingFrom($this, ['birth_year', 'death_year', 'country', 'wikidata_id']);
            $this->delete();
        });
    }
}
