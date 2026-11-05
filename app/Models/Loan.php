<?php

namespace App\Models;

use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A book lent for a while; it stays in its owner's library. The borrower is either
 * a person without an account (a name and maybe a phone, typed by the lender) or
 * another library, which accepts through a single-use link. An open link has no
 * borrower name yet; it is filled in when the loan starts.
 */
#[Fillable(['copy_id', 'lent_by', 'borrower_library_id', 'borrower_name', 'borrower_phone', 'due_on', 'token', 'expires_at', 'cancelled_at', 'returned_at'])]
#[Hidden(['token', 'borrower_phone'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    public const int LINK_LIFETIME_DAYS = 7;

    public const int TOKEN_LENGTH = 64;

    public const string TIMEZONE = 'Asia/Tashkent';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
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
    public function lentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lent_by');
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function borrowerLibrary(): BelongsTo
    {
        return $this->belongsTo(Library::class, 'borrower_library_id');
    }

    /**
     * Loans under way: someone has the book and it is not back yet.
     *
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNotNull('borrower_name')->whereNull('returned_at');
    }

    /**
     * Links waiting for another library to accept.
     *
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function pendingLink(Builder $query): void
    {
        $query->whereNull('borrower_name')
            ->whereNotNull('token')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }

    public static function newLinkAttributes(): array
    {
        return [
            'token' => Str::random(self::TOKEN_LENGTH),
            'expires_at' => now()->addDays(self::LINK_LIFETIME_DAYS),
        ];
    }

    public function isUsableLink(): bool
    {
        return $this->borrower_name === null
            && $this->token !== null
            && $this->cancelled_at === null
            && $this->expires_at?->isFuture() === true;
    }

    public function isOverdue(): bool
    {
        return $this->returned_at === null
            && $this->due_on !== null
            && $this->due_on->toDateString() < now(self::TIMEZONE)->toDateString();
    }

    public function cancelLink(): void
    {
        static::query()->whereKey($this->getKey())->pendingLink()->update(['cancelled_at' => now()]);

        $this->refresh();
    }

    /**
     * The book is back. The phone number is someone else's personal data and is
     * no longer needed, so it is erased.
     */
    public function markReturned(): void
    {
        $this->forceFill(['returned_at' => now(), 'borrower_phone' => null])->save();
    }

    /**
     * Start the loan to another library. The conditional UPDATE makes the link
     * single use even under concurrent requests. Returns false when the link is
     * no longer usable, the book is already out, or it is that library's own book.
     */
    public function acceptFor(User $user, Library $library): bool
    {
        return DB::transaction(function () use ($user, $library): bool {
            $copy = Copy::query()->whereKey($this->copy_id)->lockForUpdate()->first();

            if ($copy === null || $copy->isLost() || $copy->library_id === $library->getKey() || $copy->activeLoan() !== null) {
                return false;
            }

            $claimed = static::query()
                ->whereKey($this->getKey())
                ->pendingLink()
                ->update([
                    'borrower_library_id' => $library->getKey(),
                    'borrower_name' => $library->name,
                ]);

            if ($claimed === 0) {
                return false;
            }

            $user->switchToLibrary($library);

            $this->refresh();

            return true;
        });
    }
}
