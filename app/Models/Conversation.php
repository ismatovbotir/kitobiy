<?php

namespace App\Models;

use App\Notifications\NewMessageReceived;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Messages between a seller and one interested person about one listing. Only
 * the interested person can start it, and the seller chooses whether to reply.
 */
#[Fillable(['listing_id', 'buyer_id', 'seller_id', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->where('buyer_id', $user->getKey())
            ->orWhere('seller_id', $user->getKey()));
    }

    public function involves(User $user): bool
    {
        return in_array($user->getKey(), [$this->buyer_id, $this->seller_id], true);
    }

    public function otherParty(User $user): User
    {
        return $user->getKey() === $this->buyer_id ? $this->seller : $this->buyer;
    }

    /**
     * Save a message and email the other person, but only for the first unread
     * one, so a long exchange never floods their inbox. The email holds no
     * message text, and a mail failure never loses the message.
     */
    public function send(User $sender, string $body): Message
    {
        [$message, $isFirstUnread] = DB::transaction(function () use ($sender, $body): array {
            $isFirstUnread = ! $this->messages()
                ->where('sender_id', $sender->getKey())
                ->whereNull('read_at')
                ->exists();

            $message = $this->messages()->create(['sender_id' => $sender->getKey(), 'body' => $body]);
            $this->update(['last_message_at' => now()]);

            return [$message, $isFirstUnread];
        });

        if ($isFirstUnread) {
            try {
                $this->otherParty($sender)->notify(new NewMessageReceived($this));
            } catch (TransportExceptionInterface $exception) {
                report($exception);
            }
        }

        return $message;
    }

    public function markReadBy(User $reader): void
    {
        $this->messages()
            ->where('sender_id', '!=', $reader->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
