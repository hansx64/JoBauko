<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OrganizationType: string
{
    use HasOptions;

    case PrivateCompany = 'private';
    case Lgu = 'lgu';
    case NationalAgency = 'national_agency';
    case Educational = 'educational';
    case Ngo = 'ngo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PrivateCompany => 'Private Company',
            self::Lgu => 'Local Government Unit (LGU)',
            self::NationalAgency => 'National Government Agency',
            self::Educational => 'Educational Institution',
            self::Ngo => 'Non-Government Organization',
            self::Other => 'Other Organization',
        };
    }
}
