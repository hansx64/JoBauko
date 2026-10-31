<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The permission role of an account. This decides which area of the portal a
 * user can access. It is unrelated to a seeker's Classification.
 */
enum AccountType: string
{
    use HasOptions;

    case Seeker = 'seeker';
    case Organization = 'organization';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Seeker => 'Opportunity Seeker',
            self::Organization => 'Organization',
            self::Admin => 'System Administrator',
        };
    }
}
