<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EducationLevel: string
{
    use HasOptions;

    case Elementary = 'elementary';
    case JuniorHigh = 'junior_high';
    case SeniorHigh = 'senior_high';
    case Vocational = 'vocational';
    case CollegeLevel = 'college_level';
    case CollegeGraduate = 'college_graduate';
    case Masters = 'masters';
    case Doctorate = 'doctorate';

    public function label(): string
    {
        return match ($this) {
            self::Elementary => 'Elementary',
            self::JuniorHigh => 'Junior High School',
            self::SeniorHigh => 'Senior High School',
            self::Vocational => 'Vocational / TESDA',
            self::CollegeLevel => 'College Level (undergraduate)',
            self::CollegeGraduate => "College Graduate (Bachelor's)",
            self::Masters => "Master's Degree",
            self::Doctorate => 'Doctorate',
        };
    }

    /**
     * Ordering used to compare an applicant's attainment with an
     * opportunity's minimum requirement.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Elementary => 1,
            self::JuniorHigh => 2,
            self::SeniorHigh => 3,
            self::Vocational => 4,
            self::CollegeLevel => 5,
            self::CollegeGraduate => 6,
            self::Masters => 7,
            self::Doctorate => 8,
        };
    }
}
