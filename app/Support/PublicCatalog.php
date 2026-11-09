<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Rules for the public catalog pages (books, people, publishers, genres). They
 * show shared book facts only: never who owns a copy, library names or notes.
 */
class PublicCatalog
{
    /** A book with no ISBN and not from its publisher is public once this many libraries have it. */
    public const int MIN_LIBRARIES_WITHOUT_ISBN = 2;

    /** "On N shelves" is shown only from this many libraries, so a count never points at one family. */
    public const int MIN_SHELVES_SHOWN = 5;

    public const int PER_PAGE = 24;

    /**
     * The readable part of a page address, e.g. "otkan-kunlar" for "O‘tkan kunlar".
     */
    public static function slug(string $text): string
    {
        return Str::limit(Str::slug($text), 80, '') ?: '-';
    }
}
