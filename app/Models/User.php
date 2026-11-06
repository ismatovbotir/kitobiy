<?php

namespace App\Models;

use App\Enums\LibraryRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

#[Fillable(['name', 'email', 'locale', 'password'])]
#[Hidden(['password', 'remember_token', 'telegram_chat_id', 'telegram_link_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    public const int MAX_OWNED_LIBRARIES = 5;

    /** Telegram allows at most 64 characters in a Start link. */
    public const int TELEGRAM_TOKEN_LENGTH = 40;

    public const int TELEGRAM_LINK_MINUTES = 15;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'suspended_at' => 'datetime',
            'telegram_chat_id' => 'integer',
            'telegram_link_expires_at' => 'datetime',
            'current_library_id' => 'integer',
        ];
    }

    /**
     * Mail and notifications go out in the user's own interface language.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * May use the /admin area (gate "super-admin"). Set only by the kitobiy:make-super-admin command.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * A one-time link that opens the bot and connects this account. Only its hash
     * is stored, so a database leak can't be used to connect someone's account.
     */
    public function newTelegramLink(string $botUsername): string
    {
        $token = Str::random(self::TELEGRAM_TOKEN_LENGTH);

        $this->forceFill([
            'telegram_link_token' => hash('sha256', $token),
            'telegram_link_expires_at' => now()->addMinutes(self::TELEGRAM_LINK_MINUTES),
        ])->save();

        return "https://t.me/{$botUsername}?start={$token}";
    }

    /**
     * Connect the chat that pressed Start. A chat belongs to one account, so any
     * earlier account of that chat is disconnected. Returns null for a bad or old link.
     */
    public static function connectTelegramByToken(string $token, int $chatId): ?self
    {
        return DB::transaction(function () use ($token, $chatId): ?self {
            $user = static::query()
                ->where('telegram_link_token', hash('sha256', $token))
                ->where('telegram_link_expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return null;
            }

            static::query()->where('telegram_chat_id', $chatId)->whereKeyNot($user->getKey())->update(['telegram_chat_id' => null]);

            $user->forceFill([
                'telegram_chat_id' => $chatId,
                'telegram_link_token' => null,
                'telegram_link_expires_at' => null,
            ])->save();

            return $user;
        });
    }

    public function disconnectTelegram(): void
    {
        $this->forceFill([
            'telegram_chat_id' => null,
            'telegram_link_token' => null,
            'telegram_link_expires_at' => null,
        ])->save();
    }

    /**
     * @return HasOne<CollectorProfile, $this>
     */
    public function collectorProfile(): HasOne
    {
        return $this->hasOne(CollectorProfile::class);
    }

    /**
     * The name other people see on the market. Never the account name, so nobody
     * shows their full name by accident.
     */
    public function marketName(): string
    {
        return $this->collectorProfile?->display_name ?? __('Someone');
    }

    /**
     * Offering books and sending messages need a name to show, and no suspension.
     */
    public function canUseMarket(): bool
    {
        return ! $this->isSuspended() && $this->collectorProfile()->exists();
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * A suspended user keeps their own library but disappears from the market.
     */
    public function suspend(): void
    {
        $this->forceFill(['suspended_at' => now()])->save();
    }

    public function restoreFromSuspension(): void
    {
        $this->forceFill(['suspended_at' => null])->save();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id')->withTimestamps();
    }

    public function block(User $other): void
    {
        if ($other->isNot($this)) {
            $this->blockedUsers()->syncWithoutDetaching([$other->getKey()]);
        }
    }

    public function unblock(User $other): void
    {
        $this->blockedUsers()->detach($other->getKey());
    }

    public function hasBlockWith(User $other): bool
    {
        return DB::table('user_blocks')
            ->where(fn ($query) => $query->where('blocker_id', $this->getKey())->where('blocked_id', $other->getKey()))
            ->orWhere(fn ($query) => $query->where('blocker_id', $other->getKey())->where('blocked_id', $this->getKey()))
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function blockedUserIdsBothWays(): array
    {
        return DB::table('user_blocks')
            ->where('blocker_id', $this->getKey())
            ->pluck('blocked_id')
            ->merge(DB::table('user_blocks')->where('blocked_id', $this->getKey())->pluck('blocker_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function unreadMessagesCount(): int
    {
        return Message::query()
            ->whereNull('read_at')
            ->whereNull('hidden_at')
            ->where('sender_id', '!=', $this->getKey())
            ->whereHas('conversation', fn ($conversation) => $conversation->involving($this))
            ->count();
    }

    /**
     * Libraries the user belongs to, with their role on the pivot. A user may
     * belong to many libraries and works in one at a time (currentLibrary).
     *
     * @return BelongsToMany<Library, $this, Membership, 'membership'>
     */
    public function libraries(): BelongsToMany
    {
        return $this->belongsToMany(Library::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Library, $this, Membership, 'membership'>
     */
    public function ownedLibraries(): BelongsToMany
    {
        return $this->libraries()->wherePivot('role', LibraryRole::Owner->value);
    }

    /**
     * The library the user works in: the one they last switched to, else the
     * first one they joined. An id of a library they left is ignored.
     */
    public function currentLibrary(): ?Library
    {
        if ($this->current_library_id !== null) {
            $library = $this->libraries()->whereKey($this->current_library_id)->first();

            if ($library !== null) {
                return $library;
            }
        }

        return $this->libraries()->orderBy('library_user.id')->first();
    }

    /**
     * Work in another of the user's libraries from now on.
     */
    public function switchToLibrary(Library $library): void
    {
        if (! $library->hasMember($this)) {
            throw new InvalidArgumentException('The user is not a member of this library.');
        }

        $this->forceFill(['current_library_id' => $library->id])->save();
    }

    /**
     * Owners may create a few libraries (e.g. home, summer house), not unlimited ones.
     */
    public function canCreateLibrary(): bool
    {
        return $this->ownedLibraries()->count() < self::MAX_OWNED_LIBRARIES;
    }

    /**
     * The user's library, creating their default one when they have none,
     * so a book always has a library to go into.
     */
    public function libraryOrCreateDefault(): Library
    {
        return $this->currentLibrary() ?? $this->createLibrary($this->defaultLibraryName());
    }

    /**
     * E.g. "Botir’s library", in the current interface language.
     */
    public function defaultLibraryName(): string
    {
        $firstName = Str::of($this->name)->trim()->before(' ')->limit(60, '')->toString();

        return __(':name’s library', ['name' => $firstName !== '' ? $firstName : $this->name]);
    }

    /**
     * Create a library with this user as its owner.
     */
    public function createLibrary(string $name): Library
    {
        return DB::transaction(function () use ($name): Library {
            $library = Library::create(['name' => $name]);
            $library->members()->attach($this, ['role' => LibraryRole::Owner->value]);

            return $library;
        });
    }
}
