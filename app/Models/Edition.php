<?php

namespace App\Models;

use App\Enums\ContributorRole;
use App\Enums\EditionSource;
use App\Support\SearchKey;
use Database\Factories\EditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A printed edition. Shared catalog across all libraries; never library-scoped.
 */
#[Fillable([
    'work_id', 'title', 'subtitle', 'language', 'isbn_13', 'isbn_10',
    'publisher_id', 'published_year', 'page_count', 'cover_url', 'source', 'source_id', 'created_by',
])]
class Edition extends Model
{
    /** @use HasFactory<EditionFactory> */
    use HasFactory;

    /** How many genres one edition may have. */
    public const int MAX_GENRES = 3;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'manual',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => EditionSource::class,
            'published_year' => 'integer',
            'page_count' => 'integer',
        ];
    }

    /**
     * Strip hyphens and whitespace and uppercase the "X" check digit.
     * Returns null for null or empty input. Does not validate checksums.
     */
    public static function normalizeIsbn(?string $isbn): ?string
    {
        if ($isbn === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[\s\-]+/u', '', $isbn));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Title and subtitle each refresh `search_text`, the folded SearchKey of
     * both, whichever of them is set last.
     *
     * @return Attribute<string, string>
     */
    protected function title(): Attribute
    {
        return Attribute::make(set: fn (?string $value, array $attributes): array => [
            'title' => $value,
            'search_text' => mb_substr(SearchKey::from($value.' '.($attributes['subtitle'] ?? '')), 0, 520),
        ]);
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function subtitle(): Attribute
    {
        return Attribute::make(set: fn (?string $value, array $attributes): array => [
            'subtitle' => $value,
            'search_text' => mb_substr(SearchKey::from(($attributes['title'] ?? '').' '.$value), 0, 520),
        ]);
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function isbn13(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => static::normalizeIsbn($value));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function isbn10(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => static::normalizeIsbn($value));
    }

    /**
     * The cover to show: an uploaded photo wins over the one found by lookup.
     *
     * @return Attribute<string|null, never>
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->cover_path !== null
            ? Storage::disk(config('catalog.cover_disk'))->url($this->cover_path)
            : $this->cover_url);
    }

    /**
     * Entered by its publisher: the details are kept by the publisher, not by readers.
     */
    public function isFromPublisher(): bool
    {
        return $this->source === EditionSource::Publisher;
    }

    public function hasCover(): bool
    {
        return $this->cover_path !== null || $this->cover_url !== null;
    }

    /**
     * Point the edition at a newly stored photo (or none) and delete the previous one.
     */
    public function setUploadedCover(?string $path): void
    {
        $previousPath = $this->cover_path;

        $this->forceFill(['cover_path' => $path])->save();

        if ($previousPath !== null) {
            Storage::disk(config('catalog.cover_disk'))->delete($previousPath);
        }
    }

    /**
     * Match an ISBN in any format against both ISBN-13 and ISBN-10 columns.
     *
     * @param  Builder<Edition>  $query
     */
    #[Scope]
    protected function whereIsbn(Builder $query, string $isbn): void
    {
        $normalized = static::normalizeIsbn($isbn);

        $query->where(fn (Builder $inner) => $inner
            ->where('isbn_13', $normalized)
            ->orWhere('isbn_10', $normalized));
    }

    /**
     * @return BelongsTo<Work, $this>
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * The people in the author role. Attaching through it stores role = author.
     *
     * @return BelongsToMany<Author, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->contributorsWithRole(ContributorRole::Author);
    }

    /**
     * Everyone who contributed, in any role (author, translator, editor…).
     *
     * @return BelongsToMany<Author, $this>
     */
    public function contributors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->withPivot('role', 'position')
            ->orderByPivot('position');
    }

    /**
     * @return BelongsToMany<Author, $this>
     */
    public function contributorsWithRole(ContributorRole $role): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->withPivotValue('role', $role->value)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * Contributors other than authors, grouped by role in display order. Uses
     * the loaded `contributors` relation.
     *
     * @return array<string, array{role: ContributorRole, people: Collection<int, Author>}>
     */
    public function otherContributorsByRole(): array
    {
        $groups = [];

        foreach (ContributorRole::cases() as $role) {
            if ($role === ContributorRole::Author) {
                continue;
            }

            $people = $this->contributors->filter(fn (Author $person): bool => $person->pivot->role === $role->value)->values();

            if ($people->isNotEmpty()) {
                $groups[$role->value] = ['role' => $role, 'people' => $people];
            }
        }

        return $groups;
    }

    /**
     * @return HasMany<Copy, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(Copy::class);
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * Set the publisher by name, reusing a known publisher (name or alias) or
     * creating an unverified one. A blank name clears it. Does not save.
     */
    public function setPublisherName(?string $name): void
    {
        $this->publisher()->associate(Publisher::findOrCreateByName($name));
    }

    /**
     * The genres of this edition, in display order.
     *
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)->orderBy('genres.position')->orderBy('genres.id');
    }

    /**
     * Replace the genres with the given slugs: the first MAX_GENRES known ones.
     * Unknown slugs are ignored, and so are inactive genres the edition does not have yet.
     *
     * @param  array<int, string>  $slugs
     */
    public function syncGenres(array $slugs): void
    {
        $slugs = array_values(array_unique(array_filter($slugs, 'is_string')));
        $currentIds = $this->genres()->pluck('genres.id')->all();

        $genres = Genre::query()
            ->whereIn('slug', $slugs)
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $currentIds))
            ->get()
            ->sortBy(fn (Genre $genre): int => array_search($genre->slug, $slugs, true))
            ->take(self::MAX_GENRES);

        $this->genres()->sync($genres->modelKeys());
        $this->unsetRelation('genres');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Replace the authors with a comma-separated list of names, keeping their order.
     */
    public function syncAuthorNames(string $names): void
    {
        $this->syncContributorNames(ContributorRole::Author, $names);
    }

    /**
     * Replace the people in one role with a comma-separated list of names,
     * keeping their order and reusing existing people by exact name or alias.
     * Unverified people removed from their last edition are deleted.
     */
    public function syncContributorNames(ContributorRole $role, string $names): void
    {
        $authorIds = collect(explode(',', $names))
            ->map(fn (string $name): string => mb_substr(trim($name), 0, 255))
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(fn (string $name, int $position): array => [
                Author::findOrCreateByName($name)->id => ['position' => $position],
            ]);

        $changes = $this->contributorsWithRole($role)->sync($authorIds->all());

        // Typed-in people left without any edition (e.g. a misspelled name that was corrected) are removed,
        // so they stop showing up in name suggestions. Imported or verified people are reference data and stay.
        Author::query()
            ->whereKey($changes['detached'])
            ->where('is_verified', false)
            ->whereNull('wikidata_id')
            ->whereDoesntHave('editions')
            ->delete();

        $this->unsetRelation('contributors');
    }

    /**
     * Whether only the given library holds copies of this edition, so its members
     * may correct the shared catalog entry without affecting anyone else.
     */
    public function isHeldOnlyBy(Library $library): bool
    {
        return ! $this->copies()->where('library_id', '!=', $library->getKey())->exists();
    }

    /**
     * Mark this edition and the other one as the same work (e.g. original and
     * translation). When both already belong to different works, the works are
     * merged into the other edition's work.
     */
    public function linkToSameWorkAs(Edition $other): void
    {
        if ($other->is($this)) {
            return;
        }

        DB::transaction(function () use ($other): void {
            $work = $other->work ?? $this->work ?? Work::create([
                'original_title' => $other->title,
                'original_language' => $other->language,
            ]);

            if ($this->work_id !== null && $this->work_id !== $work->id) {
                $oldWorkId = $this->work_id;
                Edition::query()->where('work_id', $oldWorkId)->update(['work_id' => $work->id]);
                Work::query()->whereKey($oldWorkId)->delete();
            }

            $this->forceFill(['work_id' => $work->id])->save();
            $other->forceFill(['work_id' => $work->id])->save();
        });
    }

    /**
     * Remove this edition from its work. A work left with a single edition is
     * deleted, since it no longer links anything.
     */
    public function unlinkFromWork(): void
    {
        if ($this->work_id === null) {
            return;
        }

        DB::transaction(function (): void {
            $workId = $this->work_id;
            $this->forceFill(['work_id' => null])->save();

            if (Edition::query()->where('work_id', $workId)->count() < 2) {
                Edition::query()->where('work_id', $workId)->update(['work_id' => null]);
                Work::query()->whereKey($workId)->delete();
            }
        });
    }
}
