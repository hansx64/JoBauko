<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Stored lifecycle state. "Expired" is not stored: it is a published
 * opportunity whose application deadline has passed.
 */
enum OpportunityStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
            self::Suspended => 'Suspended',
        };
    }
}
