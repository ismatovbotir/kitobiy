<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Scam = 'scam';
    case Abuse = 'abuse';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => __('Spam'),
            self::Scam => __('Looks like a scam'),
            self::Abuse => __('Rude or abusive'),
            self::Other => __('Something else'),
        };
    }
}
