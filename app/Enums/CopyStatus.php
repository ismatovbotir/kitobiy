<?php

namespace App\Enums;

/**
 * Whether a book is still on the shelves. A lost book stays in the catalog, but
 * friends don't see it on the gift page and it cannot be given away.
 */
enum CopyStatus: string
{
    case Available = 'available';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('On the shelf'),
            self::Lost => __('Lost'),
        };
    }
}
