<?php

namespace App\Policies;

use App\Models\Library;
use App\Models\User;

class LibraryPolicy
{
    /**
     * Any member may open the library and see who else is in it.
     */
    public function view(User $user, Library $library): bool
    {
        return $library->hasMember($user);
    }

    /**
     * Everyone but viewers catalogs books: adds copies and tags.
     */
    public function addBooks(User $user, Library $library): bool
    {
        return $library->roleOf($user)?->canEditBooks() ?? false;
    }

    /**
     * Everyone who catalogs may also add and remove wishes; viewers only look.
     */
    public function updateWishlist(User $user, Library $library): bool
    {
        return $this->addBooks($user, $library);
    }

    /**
     * A borrower's phone number is someone else's personal data, so only the
     * people who run the library see it.
     */
    public function viewBorrowerPhone(User $user, Library $library): bool
    {
        return $library->roleOf($user)?->canManageMembers() ?? false;
    }

    /**
     * Owners and admins may rename the library.
     */
    public function update(User $user, Library $library): bool
    {
        return $library->roleOf($user)?->canManageMembers() ?? false;
    }

    /**
     * Owners and admins may invite people, revoke invitations and remove or
     * re-role the people below them (see LibraryRole::canManage).
     */
    public function manageMembers(User $user, Library $library): bool
    {
        return $library->roleOf($user)?->canManageMembers() ?? false;
    }

    /**
     * Owners and admins turn the gift page on or off and renew its link.
     */
    public function share(User $user, Library $library): bool
    {
        return $library->roleOf($user)?->canManageMembers() ?? false;
    }

    /**
     * Members may leave; owners may not, so a library is never left without one.
     */
    public function leave(User $user, Library $library): bool
    {
        return $library->hasMember($user) && ! $library->isOwnedBy($user);
    }
}
