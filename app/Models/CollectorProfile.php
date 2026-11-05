<?php

namespace App\Models;

use Database\Factories\CollectorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * What a user chooses to show about themselves as a collector. It is off until
 * they turn it on, opens only by a secret link (there is no directory), and
 * holds only a name they typed and a short bio, never their account details.
 */
#[Fillable(['user_id', 'display_name', 'bio'])]
#[Hidden(['share_token'])]
class CollectorProfile extends Model
{
    /** @use HasFactory<CollectorProfileFactory> */
    use HasFactory;

    public const int SHARE_TOKEN_LENGTH = 32;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isShared(): bool
    {
        return $this->share_token !== null;
    }

    /**
     * Turn the profile on with a new random link. Any old link stops working.
     */
    public function shareWithNewLink(): void
    {
        $this->forceFill(['share_token' => Str::random(self::SHARE_TOKEN_LENGTH)])->save();
    }

    /**
     * Turn the profile off; the link stops working.
     */
    public function stopSharing(): void
    {
        $this->forceFill(['share_token' => null])->save();
    }

    public function shareUrl(): ?string
    {
        return $this->isShared() ? route('collectors.show', $this->share_token) : null;
    }
}
