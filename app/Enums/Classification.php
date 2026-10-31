<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A seeker's current circumstances. This is a profile attribute used for
 * recommendations and notification defaults, never for access control.
 */
enum Classification: string
{
    use HasOptions;

    case Student = 'student';
    case FreshGraduate = 'fresh_graduate';
    case Employed = 'employed';
    case Unemployed = 'unemployed';
    case SelfEmployed = 'self_employed';
    case CareerShifter = 'career_shifter';
    case OutOfSchoolYouth = 'out_of_school_youth';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::FreshGraduate => 'Fresh Graduate',
            self::Employed => 'Employed',
            self::Unemployed => 'Unemployed',
            self::SelfEmployed => 'Self-Employed',
            self::CareerShifter => 'Career Shifter',
            self::OutOfSchoolYouth => 'Out-of-School Youth',
            self::Other => 'Other',
        };
    }

    /**
     * Opportunity topics pre-selected for notifications at registration.
     * These are starting suggestions only; the user can change them freely.
     *
     * @return list<NotificationTopic>
     */
    public function defaultNotificationTopics(): array
    {
        return match ($this) {
            self::Student => [NotificationTopic::Scholarships],
            self::FreshGraduate => [NotificationTopic::Jobs],
            self::Employed, self::Unemployed => [NotificationTopic::Jobs, NotificationTopic::Livelihood],
            self::SelfEmployed => [NotificationTopic::Livelihood],
            self::CareerShifter => [NotificationTopic::Jobs, NotificationTopic::Training],
            self::OutOfSchoolYouth => [NotificationTopic::Livelihood, NotificationTopic::Scholarships],
            self::Other => [],
        };
    }
}
