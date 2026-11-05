<?php

namespace App\Models;

use App\Enums\BookCondition;
use App\Enums\CopyStatus;
use App\Enums\ListingKind;
use App\Enums\ListingStatus;
use App\Support\SearchKey;
use Database\Factories\CopyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * One physical book in one library.
 */
#[Fillable(['library_id', 'edition_id', 'added_by', 'notes', 'condition', 'status'])]
class Copy extends Model
{
    /** @use HasFactory<CopyFactory> */
    use HasFactory, HasUlids;

    /**
     * The Book ID: the public link and QR code of this physical book. It can't be
     * guessed and moves with the book. The integer primary key stays as it is.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoan(): ?Loan
    {
        return $this->loans()->active()->latest('id')->first();
    }

    public function pendingLoanLink(): ?Loan
    {
        return $this->loans()->pendingLink()->latest('id')->first();
    }

    /**
     * Lend this book to someone without an account.
     */
    public function lendToPerson(User $lender, string $name, ?string $phone, ?string $dueOn): Loan
    {
        return DB::transaction(function () use ($lender, $name, $phone, $dueOn): Loan {
            static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            return $this->activeLoan() ?? $this->loans()->create([
                'lent_by' => $lender->getKey(),
                'borrower_name' => $name,
                'borrower_phone' => $phone,
                'due_on' => $dueOn,
            ]);
        });
    }

    /**
     * Start lending to another library, or return the link already open, so one
     * book never has two links that could send it to two places.
     */
    public function offerLoan(User $lender, ?string $dueOn): Loan
    {
        return DB::transaction(function () use ($lender, $dueOn): Loan {
            static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            return $this->pendingLoanLink() ?? $this->loans()->create([
                'lent_by' => $lender->getKey(),
                'due_on' => $dueOn,
                ...Loan::newLinkAttributes(),
            ]);
        });
    }

    /**
     * @return HasMany<FoundReport, $this>
     */
    public function foundReports(): HasMany
    {
        return $this->hasMany(FoundReport::class);
    }

    public function foundPageUrl(): string
    {
        return route('copies.found', $this->public_id);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_from_gift_page' => 'boolean',
            'condition' => BookCondition::class,
            'status' => CopyStatus::class,
        ];
    }

    /**
     * The status column has a database default, which a freshly created model
     * would not know about until it is reloaded.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'available',
    ];

    /**
     * A deleted book can't be sold. The listing itself stays, so its conversations
     * and reports survive.
     */
    protected static function booted(): void
    {
        static::deleting(fn (Copy $copy) => $copy->closeListings());
    }

    public function isLost(): bool
    {
        return $this->status === CopyStatus::Lost;
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function activeListing(): ?Listing
    {
        return $this->listings()->where('status', ListingStatus::Active)->latest('id')->first();
    }

    /**
     * Offer this book, or return the listing already open for it, so one book
     * is never on offer twice.
     */
    public function createListing(User $seller, ListingKind $kind, ?int $price, ?string $note): Listing
    {
        return DB::transaction(function () use ($seller, $kind, $price, $note): Listing {
            static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            return $this->activeListing() ?? $this->listings()->create([
                'seller_id' => $seller->getKey(),
                'title' => $this->edition->title,
                'kind' => $kind,
                'price' => $kind === ListingKind::Sell ? $price : null,
                'note' => $note,
            ]);
        });
    }

    public function closeListings(): void
    {
        $this->listings()
            ->where('status', ListingStatus::Active)
            ->update(['status' => ListingStatus::Closed, 'closed_at' => now()]);
    }

    /**
     * Start giving this book away, or return the link already open for it, so
     * one book never has two links that could send it to two libraries.
     */
    public function offerTransfer(User $user): CopyTransfer
    {
        return DB::transaction(function () use ($user): CopyTransfer {
            static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            return $this->pendingTransfer() ?? $this->transfers()->create(['offered_by' => $user->getKey()]);
        });
    }

    public function pendingTransfer(): ?CopyTransfer
    {
        return $this->transfers()->pending()->latest('id')->first();
    }

    /**
     * @return HasMany<CopyTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(CopyTransfer::class);
    }

    /**
     * The libraries this book came from, oldest first.
     *
     * @return HasMany<CopyTransfer, $this>
     */
    public function transferHistory(): HasMany
    {
        return $this->transfers()->whereNotNull('accepted_at')->oldest('accepted_at')->oldest('id');
    }

    /**
     * Friends see editions, not copies, so hiding counts for the whole edition
     * in this library: one hidden copy keeps the book off the gift page.
     */
    public function isHiddenFromGiftPage(): bool
    {
        return $this->library->copies()
            ->where('edition_id', $this->edition_id)
            ->where('hidden_from_gift_page', true)
            ->exists();
    }

    public function setHiddenFromGiftPage(bool $hidden): void
    {
        $this->library->copies()
            ->where('edition_id', $this->edition_id)
            ->update(['hidden_from_gift_page' => $hidden]);
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Copies that share their edition with another copy in the same library.
     * Correlated EXISTS backed by the (library_id, edition_id) index.
     *
     * @param  Builder<Copy>  $query
     */
    #[Scope]
    protected function exactDuplicates(Builder $query): void
    {
        $query->whereExists(fn (QueryBuilder $other) => $other
            ->selectRaw('1')
            ->from('copies as other_copies')
            ->whereColumn('other_copies.library_id', $this->qualifyColumn('library_id'))
            ->whereColumn('other_copies.edition_id', $this->qualifyColumn('edition_id'))
            ->whereColumn('other_copies.id', '!=', $this->qualifyColumn('id')));
    }

    /**
     * Copies whose edition belongs to a work that the same library also holds
     * in a different edition (e.g. original and translation). Editions without
     * a work never match.
     *
     * @param  Builder<Copy>  $query
     */
    #[Scope]
    protected function sameWorkDuplicates(Builder $query): void
    {
        $query->whereExists(fn (QueryBuilder $other) => $other
            ->selectRaw('1')
            ->from('copies as other_copies')
            ->join('editions as other_editions', 'other_editions.id', '=', 'other_copies.edition_id')
            ->join('editions as own_editions', 'own_editions.work_id', '=', 'other_editions.work_id')
            ->whereColumn('own_editions.id', $this->qualifyColumn('edition_id'))
            ->whereColumn('other_copies.library_id', $this->qualifyColumn('library_id'))
            ->whereColumn('other_copies.edition_id', '!=', $this->qualifyColumn('edition_id')));
    }

    /**
     * Copies whose edition matches the search term by title, subtitle, any
     * contributor (author, translator…, also by alias) or ISBN. Text is compared
     * as folded SearchKeys, so case, apostrophes and Latin/Cyrillic don't matter.
     *
     * @param  Builder<Copy>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $pattern = SearchKey::containsPattern($term);
        $isbn = Edition::normalizeIsbn($term) ?? '';

        $query->whereHas('edition', fn (Builder $edition) => $edition
            ->whereLike('search_text', $pattern ?? '')
            ->orWhere('isbn_13', $isbn)
            ->orWhere('isbn_10', $isbn)
            ->orWhereHas('contributors', fn (Builder $person) => $person->nameOrAliasContains($term)));
    }
}
