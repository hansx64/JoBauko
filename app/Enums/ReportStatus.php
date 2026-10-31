<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ReportStatus: string
{
    use HasOptions;

    case Open = 'open';
    case ActionTaken = 'action_taken';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::ActionTaken => 'Action taken',
            self::Dismissed => 'Dismissed',
        };
    }
}
