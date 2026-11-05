<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('Running'),
            self::Succeeded => __('Done'),
            self::Failed => __('Failed'),
        };
    }
}
