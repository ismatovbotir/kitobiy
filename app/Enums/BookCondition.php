<?php

namespace App\Enums;

/**
 * How worn one physical book is. It belongs to the book, so it moves with it
 * when the book is given to another library.
 */
enum BookCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::New => __('Like new'),
            self::Good => __('Good'),
            self::Fair => __('Fair'),
            self::Poor => __('Poor'),
        };
    }
}
