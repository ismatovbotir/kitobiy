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
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    public const int MAX_OWNED_LIBRARIES = 5;

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
     * @return HasOne<CollectorProfile, $this>
     */
    public function collectorProfile(): HasOne
    {
        return $this->hasOne(CollectorProfile::class);
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
