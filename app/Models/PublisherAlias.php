<?php

namespace App\Models;

use App\Models\Concerns\HasSearchName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another name of a publisher, e.g. the Cyrillic spelling or an old name.
 * `language` is a tag like uz-Latn, uz-Cyrl, ru or en.
 */
#[Fillable(['publisher_id', 'name', 'language'])]
class PublisherAlias extends Model
{
    use HasSearchName;

    public $timestamps = false;

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }
}
