<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'sender_id', 'body', 'read_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public const int MAX_LENGTH = 1000;

    public const int MAX_PER_MINUTE = 20;

    /** How many new people one user may contact in an hour, to limit spam. */
    public const int MAX_NEW_CONVERSATIONS_PER_HOUR = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Times are stored in UTC; people read them in Uzbekistan time.
     */
    public function sentAtLabel(): string
    {
        return $this->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    public function hideByModerator(): void
    {
        $this->forceFill(['hidden_at' => now()])->save();
    }
}
