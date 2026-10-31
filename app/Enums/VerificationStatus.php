<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum VerificationStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending verification',
            self::Verified => 'Verified',
            self::Rejected => 'Verification rejected',
            self::Suspended => 'Suspended',
        };
    }
}
