<?php

namespace App\Enums;

enum ReportOutcome: string
{
    case Dismissed = 'dismissed';
    case ContentHidden = 'content_hidden';
    case UserSuspended = 'user_suspended';
}
