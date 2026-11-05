<?php

namespace App\Models;

use App\Enums\ListingKind;
use App\Enums\ListingStatus;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One book offered for sale or swap. Kitobiy only connects people: no payment or
 * delivery happens here. The title is kept as text so conversations and reports
 * still make sense after the book is deleted or has moved on.
 */
#[Fillable(['copy_id', 'seller_id', 'title', 'kind', 'price', 'note', 'status', 'closed_at'])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * The status column has a database default, which a freshly created model
     * would not know about until it is reloaded.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ListingKind::class,
            'status' => ListingStatus::class,
            'closed_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Copy, $this>
     */
    public function copy(): BelongsTo
    {
        return $this->belongsTo(Copy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Listings other people may see: open, not hidden by a moderator, the book
     * still exists and is not out on loan, the seller is not suspended and still
     * belongs to its library.
     *
     * @param  Builder<Listing>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('status', ListingStatus::Active)
            ->whereNull('hidden_at')
            ->whereNotNull('copy_id')
            ->whereDoesntHave('copy.loans', fn (Builder $loan) => $loan->active())
            ->whereHas('seller', fn (Builder $seller) => $seller->whereNull('suspended_at'))
            ->whereExists(fn (QueryBuilder $membership) => $membership
                ->selectRaw('1')
                ->from('library_user')
                ->join('copies', 'copies.library_id', '=', 'library_user.library_id')
                ->whereColumn('copies.id', 'listings.copy_id')
                ->whereColumn('library_user.user_id', 'listings.seller_id'));
    }

    /**
     * Visible listings, without anyone the user has blocked or been blocked by.
     *
     * @param  Builder<Listing>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->visible()->whereNotIn('seller_id', $user->blockedUserIdsBothWays());
    }

    public function isVisible(): bool
    {
        return static::query()->visible()->whereKey($this->getKey())->exists();
    }

    public function isActive(): bool
    {
        return $this->status === ListingStatus::Active;
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    public function close(): void
    {
        $this->update(['status' => ListingStatus::Closed, 'closed_at' => now()]);
    }

    public function hideByModerator(): void
    {
        $this->forceFill(['hidden_at' => now()])->save();
    }

    public function priceLabel(): ?string
    {
        return $this->price === null ? null : number_format($this->price, 0, '.', ' ').' '.__('so‘m');
    }
}
