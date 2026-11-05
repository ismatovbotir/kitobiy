<?php

namespace App\Models;

use Database\Factories\CopyTransferFactory;
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
 * A book given to another library for good. It starts as a single-use link the
 * giver sends by hand; the book moves only when someone accepts. An accepted
 * transfer stays as the book's history, with the old library's name as text so
 * the line survives that library being renamed or deleted.
 */
#[Fillable(['copy_id', 'offered_by', 'token', 'expires_at', 'cancelled_at', 'accepted_at', 'accepted_by', 'from_library_name'])]
#[Hidden(['token'])]
class CopyTransfer extends Model
{
    /** @use HasFactory<CopyTransferFactory> */
    use HasFactory;

    public const int LIFETIME_DAYS = 7;

    public const int TOKEN_LENGTH = 64;

    /**
     * Fill in a random token and the default expiry when they were not given.
     */
    protected static function booted(): void
    {
        static::creating(function (CopyTransfer $transfer): void {
            $transfer->token ??= Str::random(self::TOKEN_LENGTH);
            $transfer->expires_at ??= now()->addDays(self::LIFETIME_DAYS);
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
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'accepted_at' => 'datetime',
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
    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by');
    }

    /**
     * Links that can still be accepted: not accepted, not cancelled, not expired.
     *
     * @param  Builder<CopyTransfer>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null
            && $this->cancelled_at === null
            && $this->expires_at->isFuture();
    }

    public function cancel(): void
    {
        static::query()->whereKey($this->getKey())->pending()->update(['cancelled_at' => now()]);

        $this->refresh();
    }

    /**
     * Move the book into the given library. The conditional UPDATE makes the link
     * single use even under concurrent requests. Notes, tags and the gift page
     * setting belong to the old library and may be private, so they stay behind;
     * condition and status belong to the book and move with it. A wish in the new
     * library for this book is over, as when a book is added. Returns false when
     * the link was no longer usable or the book is already in that library.
     */
    public function accept(User $user, Library $library): bool
    {
        return DB::transaction(function () use ($user, $library): bool {
            $copy = Copy::query()->whereKey($this->copy_id)->lockForUpdate()->first();

            if ($copy === null || $copy->isLost() || $copy->library_id === $library->getKey()) {
                return false;
            }

            $claimed = static::query()
                ->whereKey($this->getKey())
                ->pending()
                ->update([
                    'accepted_at' => now(),
                    'accepted_by' => $user->getKey(),
                    'from_library_name' => $copy->library->name,
                ]);

            if ($claimed === 0) {
                return false;
            }

            $copy->tags()->detach();
            $copy->forceFill([
                'library_id' => $library->getKey(),
                'added_by' => $user->getKey(),
                'notes' => null,
                'hidden_from_gift_page' => false,
            ])->save();

            $library->removeWishesFulfilledBy($copy->edition);
            $user->switchToLibrary($library);

            $this->refresh();

            return true;
        });
    }
}
