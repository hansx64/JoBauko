<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OpportunityCategory: string
{
    use HasOptions;

    case Job = 'job';
    case Scholarship = 'scholarship';
    case Livelihood = 'livelihood';

    public function label(): string
    {
        return match ($this) {
            self::Job => 'Job',
            self::Scholarship => 'Scholarship',
            self::Livelihood => 'Livelihood Program',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Job => 'Jobs',
            self::Scholarship => 'Scholarships',
            self::Livelihood => 'Livelihood Programs',
        };
    }
}
