<?php

namespace App\Enums;

/**
 * What a Wikidata import run brings in.
 */
enum ImportType: string
{
    case Publishers = 'publishers';
    case Authors = 'authors';

    public function label(): string
    {
        return match ($this) {
            self::Publishers => __('Publishers'),
            self::Authors => __('People'),
        };
    }
}
