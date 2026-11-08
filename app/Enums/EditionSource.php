<?php

namespace App\Enums;

/**
 * Where the catalog data of an edition came from.
 */
enum EditionSource: string
{
    case Manual = 'manual';
    case OpenLibrary = 'open_library';
    case GoogleBooks = 'google_books';
    /** Entered by the publisher itself in the publisher area; readers cannot change it. */
    case Publisher = 'publisher';

    /**
     * The service name as shown to users (a brand name, never translated),
     * or "Publisher" for books the publisher entered.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Kitobiy',
            self::OpenLibrary => 'Open Library',
            self::GoogleBooks => 'Google Books',
            self::Publisher => __('Publisher'),
        };
    }
}
