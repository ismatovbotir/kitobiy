<?php

namespace App\Policies;

use App\Models\Publisher;
use App\Models\User;

class PublisherPolicy
{
    /**
     * Keep the publisher's book list: approved members only, and not while suspended.
     */
    public function manageBooks(User $user, Publisher $publisher): bool
    {
        return ! $user->isSuspended() && $publisher->isManagedBy($user);
    }
}
