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

    /**
     * The service name as shown to users (a brand name, never translated).
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Kitobiy',
            self::OpenLibrary => 'Open Library',
            self::GoogleBooks => 'Google Books',
        };
    }
}
