<?php

namespace App\Models;

use App\Models\Concerns\HasSearchName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another name of a person, e.g. the Cyrillic spelling ("Абдулла Қодирий")
 * or a pen name. `language` is a tag like uz-Latn, uz-Cyrl, ru or en.
 */
#[Fillable(['author_id', 'name', 'language'])]
class AuthorAlias extends Model
{
    use HasSearchName;

    public $timestamps = false;

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
