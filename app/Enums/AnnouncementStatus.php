<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AnnouncementStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Sending = 'sending';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
        };
    }
}
