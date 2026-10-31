<?php

namespace App\Enums;

enum ResumeExtractionStatus: string
{
    case Pending = 'pending';
    case Extracted = 'extracted';
    case Failed = 'failed';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting to be read',
            self::Extracted => 'Ready for your review',
            self::Failed => 'Could not be read',
            self::Reviewed => 'Reviewed',
        };
    }
}
