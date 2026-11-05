<?php

namespace App\Models;

use App\Enums\LibraryRole;
use Database\Factories\LibraryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'is_public'])]
#[Hidden(['share_token'])]
class Library extends Model
{
    /** @use HasFactory<LibraryFactory> */
    use HasFactory;

    public const int SHARE_TOKEN_LENGTH = 32;

    /**
     * Give a new library a unique, never-changing slug (name + random suffix),
     * so renaming it later does not break shared links.
     */
    protected static function booted(): void
    {
        static::creating(function (Library $library): void {
            $library->slug ??= (Str::slug($library->name) ?: 'library').'-'.Str::lower(Str::random(6));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    /**
     * All users that belong to the library, with their role on the pivot.
     *
     * @return BelongsToMany<User, $this, Membership, 'membership'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this, Membership, 'membership'>
     */
    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('role', LibraryRole::Owner->value);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<LibraryInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(LibraryInvitation::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * @return HasMany<Copy, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(Copy::class);
    }

    /**
     * @return HasMany<WishlistItem, $this>
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * A wish is over once the book arrives, or friends would still buy it. Only
     * an exact title match counts, so "Kecha" never removes "Kecha va kunduz".
     *
     * @return int How many wishes were removed.
     */
    public function removeWishesFulfilledBy(Edition $edition): int
    {
        return $this->wishlistItems()
            ->where('search_title', WishlistItem::titleKey($edition->title))
            ->delete();
    }

    /**
     * The copies friends may see: every edition with a hidden copy stays off the page.
     *
     * @return HasMany<Copy, $this>
     */
    public function giftPageCopies(): HasMany
    {
        return $this->copies()->whereNotIn('edition_id', $this->copiesHiddenFromGiftPage()->select('edition_id'));
    }

    /**
     * @return HasMany<Copy, $this>
     */
    public function copiesHiddenFromGiftPage(): HasMany
    {
        return $this->copies()->where('hidden_from_gift_page', true);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->exists();
    }

    /**
     * The user's role in this library, or null when they are not a member.
     */
    public function roleOf(User $user): ?LibraryRole
    {
        return $this->memberships()->where('user_id', $user->getKey())->first(['role'])?->role;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owners()->whereKey($user->getKey())->exists();
    }

    /**
     * Editions this library holds more than one copy of, each with `copies_count`
     * (copies in this library only). One SQL statement.
     *
     * @return Collection<int, Edition>
     */
    public function duplicateEditions(): Collection
    {
        $copiesInLibrary = fn ($query) => $query->whereBelongsTo($this);

        return Edition::query()
            ->whereIn('id', Copy::query()
                ->whereBelongsTo($this)
                ->select('edition_id')
                ->groupBy('edition_id')
                ->havingRaw('count(*) > 1'))
            ->withCount(['copies' => $copiesInLibrary])
            ->orderBy('title')
            ->get();
    }

    /**
     * Works this library holds in more than one edition (e.g. original and a
     * translation), with only the editions present in this library eager loaded.
     *
     * @return Collection<int, Work>
     */
    public function sameWorkDuplicates(): Collection
    {
        $editionsInLibrary = fn ($query) => $query->whereHas('copies', fn ($copies) => $copies->whereBelongsTo($this));

        return Work::query()
            ->whereIn('id', Copy::query()
                ->whereBelongsTo($this)
                ->join('editions', 'editions.id', '=', 'copies.edition_id')
                ->whereNotNull('editions.work_id')
                ->select('editions.work_id')
                ->groupBy('editions.work_id')
                ->havingRaw('count(distinct copies.edition_id) > 1'))
            ->with(['editions' => $editionsInLibrary])
            ->get();
    }

    /**
     * A library the user created (by default) and never used: they are its only
     * member and it holds no books. It may be dropped when they join a family library.
     */
    public function isUnusedPersonalLibraryOf(User $user): bool
    {
        return $this->isOwnedBy($user)
            && $this->members()->count() === 1
            && $this->copies()->doesntExist();
    }

    /**
     * Whether friends can see the books through the gift page link.
     */
    public function isShared(): bool
    {
        return $this->share_token !== null;
    }

    /**
     * Turn on the gift page with a new random link. Any old link stops working.
     */
    public function shareWithNewLink(): void
    {
        $this->forceFill(['share_token' => Str::random(self::SHARE_TOKEN_LENGTH)])->save();
    }

    /**
     * Turn off the gift page; the link stops working.
     */
    public function stopSharing(): void
    {
        $this->forceFill(['share_token' => null])->save();
    }

    public function shareUrl(): ?string
    {
        return $this->isShared() ? route('gift.show', $this->share_token) : null;
    }
}
