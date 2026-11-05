<?php

namespace App\Models;

use App\Enums\LibraryRole;
use Database\Factories\LibraryInvitationFactory;
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
 * An invitation into a library. With an `email` it is addressed to one person;
 * with a null `email` it is a shareable link (e.g. sent via Telegram) that any
 * logged-in user may accept. Either way it is single use: once accepted it is dead.
 */
#[Fillable(['library_id', 'invited_by', 'email', 'token', 'role', 'expires_at', 'revoked_at', 'accepted_at', 'accepted_by'])]
#[Hidden(['token'])]
class LibraryInvitation extends Model
{
    /** @use HasFactory<LibraryInvitationFactory> */
    use HasFactory;

    public const int LIFETIME_DAYS = 7;

    public const int TOKEN_LENGTH = 64;

    /**
     * Fill in a random token and the default expiry when they were not given.
     */
    protected static function booted(): void
    {
        static::creating(function (LibraryInvitation $invitation): void {
            $invitation->token ??= Str::random(self::TOKEN_LENGTH);
            $invitation->expires_at ??= now()->addDays(self::LIFETIME_DAYS);
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
            'role' => LibraryRole::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * Invitations that can still be accepted: not accepted, not revoked, not expired.
     *
     * @param  Builder<LibraryInvitation>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function isLink(): bool
    {
        return $this->email === null;
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * Accept the invitation for the given user and add them to the library.
     * The conditional UPDATE makes acceptance single use even under concurrent
     * requests. Existing members keep their current role. A new user's only,
     * unused default library (no books, no other members) is removed, and the
     * user switches to the joined library. Returns false when the invitation
     * was no longer usable.
     */
    public function accept(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $claimed = static::query()
                ->whereKey($this->getKey())
                ->pending()
                ->update(['accepted_at' => now(), 'accepted_by' => $user->getKey()]);

            if ($claimed === 0) {
                return false;
            }

            if (! $this->library->hasMember($user)) {
                $otherLibraries = $user->libraries()->whereKeyNot($this->library_id)->get();

                if ($otherLibraries->count() === 1 && $otherLibraries->first()->isUnusedPersonalLibraryOf($user)) {
                    $otherLibraries->first()->delete();
                }

                $this->library->members()->attach($user, ['role' => $this->role->value]);
            }

            $user->switchToLibrary($this->library);

            $this->refresh();

            return true;
        });
    }
}
