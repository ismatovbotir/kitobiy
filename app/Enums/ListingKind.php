<?php

namespace App\Enums;

enum ListingKind: string
{
    case Sell = 'sell';
    case Swap = 'swap';

    public function label(): string
    {
        return match ($this) {
            self::Sell => __('For sale'),
            self::Swap => __('For swap'),
        };
    }
}
