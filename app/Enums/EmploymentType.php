<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EmploymentType: string
{
    use HasOptions;

    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contractual = 'contractual';
    case JobOrder = 'job_order';
    case Seasonal = 'seasonal';
    case Internship = 'internship';
    case Freelance = 'freelance';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full-time',
            self::PartTime => 'Part-time',
            self::Contractual => 'Contractual',
            self::JobOrder => 'Job Order',
            self::Seasonal => 'Seasonal',
            self::Internship => 'Internship / OJT',
            self::Freelance => 'Freelance',
        };
    }
}
