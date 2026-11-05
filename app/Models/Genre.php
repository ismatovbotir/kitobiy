<?php

namespace App\Models;

use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * A shared book genre, set on an edition (up to Edition::MAX_GENRES).
 * Edited by a super admin. The starting list is inserted by its migration.
 * Inactive genres stay on the editions that have them, but cannot be newly picked.
 */
#[Fillable(['slug', 'name_uz', 'name_ru', 'name_en', 'position', 'is_active'])]
class Genre extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    /** Interface locales that have a name column. */
    public const array LOCALES = ['uz', 'ru', 'en'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The name in the current interface language (uz when there is none).
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(function (): string {
            $locale = in_array(app()->getLocale(), self::LOCALES, true) ? app()->getLocale() : 'uz';

            return $this->{"name_{$locale}"} ?: $this->name_uz;
        });
    }

    /**
     * @param  Builder<Genre>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Genre>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * Genres a picker offers: the active ones plus any already chosen, in display order.
     *
     * @param  array<int, string>  $selectedSlugs
     * @return Collection<int, Genre>
     */
    public static function pickable(array $selectedSlugs = []): Collection
    {
        return static::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('slug', $selectedSlugs))
            ->ordered()
            ->get();
    }

    /**
     * @return BelongsToMany<Edition, $this>
     */
    public function editions(): BelongsToMany
    {
        return $this->belongsToMany(Edition::class);
    }
}
