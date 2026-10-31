<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum InterestCategory: string
{
    use HasOptions;

    case Job = 'job';
    case Scholarship = 'scholarship';
    case Livelihood = 'livelihood';
    case Training = 'training';

    public function label(): string
    {
        return match ($this) {
            self::Job => 'Jobs',
            self::Scholarship => 'Scholarships',
            self::Livelihood => 'Livelihood programs',
            self::Training => 'Training programs',
        };
    }
}
