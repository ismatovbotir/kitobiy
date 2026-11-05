<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Only the two people in it may read a conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->involves($user);
    }

    /**
     * A block on either side, or a suspension, stops new messages. What was
     * already said stays readable.
     */
    public function reply(User $user, Conversation $conversation): bool
    {
        return $conversation->involves($user)
            && $user->canUseMarket()
            && ! $user->hasBlockWith($conversation->otherParty($user));
    }
}
