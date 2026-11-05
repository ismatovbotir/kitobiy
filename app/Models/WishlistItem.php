<?php

namespace App\Models;

use App\Support\SearchKey;
use Database\Factories\WishlistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A book a library would love to have. Plain text, because a wished book is
 * often not in our catalog yet. Friends see the wishes on the gift page.
 */
#[Fillable(['title', 'author'])]
class WishlistItem extends Model
{
    /** @use HasFactory<WishlistItemFactory> */
    use HasFactory;

    public static function titleKey(?string $title): string
    {
        return mb_substr(SearchKey::from($title), 0, 255);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function title(): Attribute
    {
        return Attribute::make(set: fn (string $value): array => [
            'title' => $value,
            'search_title' => self::titleKey($value),
        ]);
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }
}
