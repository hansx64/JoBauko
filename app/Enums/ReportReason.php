<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ReportReason: string
{
    use HasOptions;

    case Scam = 'scam';
    case Misleading = 'misleading';
    case Discriminatory = 'discriminatory';
    case Fees = 'fees';
    case Inappropriate = 'inappropriate';
    case Outdated = 'outdated';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Scam => 'Scam or fraud',
            self::Misleading => 'False or misleading information',
            self::Discriminatory => 'Discriminatory requirements',
            self::Fees => 'Asks applicants for payment',
            self::Inappropriate => 'Offensive or inappropriate content',
            self::Outdated => 'No longer available',
            self::Other => 'Something else',
        };
    }

    /**
     * Reasons too vague to act on without an explanation.
     */
    public function requiresDetails(): bool
    {
        return in_array($this, [self::Misleading, self::Other], true);
    }
}
