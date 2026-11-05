<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /**
     * Sellers always see their own listing; others only while it is visible and
     * nobody on either side has blocked the other.
     */
    public function view(User $user, Listing $listing): bool
    {
        if ($listing->seller_id === $user->getKey()) {
            return true;
        }

        return $listing->isVisible() && ! $user->hasBlockWith($listing->seller);
    }

    /**
     * Anyone but the seller may ask about a listing they can see, once they have
     * a name to show and are not suspended.
     */
    public function contact(User $user, Listing $listing): bool
    {
        return $listing->seller_id !== $user->getKey()
            && $user->canUseMarket()
            && $this->view($user, $listing);
    }

    /**
     * The seller may close it, and so may whoever runs the book's library.
     */
    public function close(User $user, Listing $listing): bool
    {
        return $listing->seller_id === $user->getKey()
            || ($listing->copy?->library->roleOf($user)?->canManageMembers() ?? false);
    }

    /**
     * A person can stop hearing from a seller, but not from themselves.
     */
    public function blockSeller(User $user, Listing $listing): bool
    {
        return $listing->seller_id !== $user->getKey();
    }
}
