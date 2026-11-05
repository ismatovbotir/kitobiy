<?php

namespace App\Policies;

use App\Models\Copy;
use App\Models\User;

class CopyPolicy
{
    /**
     * Any member of the copy's library may open it.
     */
    public function view(User $user, Copy $copy): bool
    {
        return $copy->library->hasMember($user);
    }

    /**
     * Everyone who catalogs (not viewers) may edit, tag or link a copy.
     */
    public function update(User $user, Copy $copy): bool
    {
        return $copy->library->roleOf($user)?->canEditBooks() ?? false;
    }

    /**
     * Everyone who catalogs may remove a copy (e.g. a book given away or added by mistake).
     */
    public function delete(User $user, Copy $copy): bool
    {
        return $this->update($user, $copy);
    }

    /**
     * Everyone who catalogs may give a book to another library, unless it is lost.
     */
    public function transfer(User $user, Copy $copy): bool
    {
        return $this->update($user, $copy) && ! $copy->isLost();
    }

    /**
     * The shared catalog entry may be corrected only while no other library
     * holds this edition, so one family's fix never changes another's books.
     */
    public function updateEdition(User $user, Copy $copy): bool
    {
        return $this->update($user, $copy) && $copy->edition->isHeldOnlyBy($copy->library);
    }

    /**
     * Genres are shared catalog data too: anyone may set them while the edition
     * has none yet; after that only a library that alone holds the edition.
     */
    public function updateGenres(User $user, Copy $copy): bool
    {
        return $this->update($user, $copy)
            && (! $copy->edition->genres()->exists() || $copy->edition->isHeldOnlyBy($copy->library));
    }

    /**
     * The cover is shared catalog data too: anyone may add one while the edition
     * has none; after that only a library that alone holds the edition may change it.
     */
    public function updateCover(User $user, Copy $copy): bool
    {
        return $this->update($user, $copy)
            && (! $copy->edition->hasCover() || $copy->edition->isHeldOnlyBy($copy->library));
    }
}
