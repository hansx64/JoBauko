<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum NotificationTopic: string
{
    use HasOptions;

    case Jobs = 'jobs';
    case Scholarships = 'scholarships';
    case Livelihood = 'livelihood';
    case Training = 'training';
    case StatusUpdates = 'status_updates';
    case Deadlines = 'deadlines';
    case Announcements = 'announcements';

    public function label(): string
    {
        return match ($this) {
            self::Jobs => 'Job Opportunities',
            self::Scholarships => 'Scholarship Opportunities',
            self::Livelihood => 'Livelihood Opportunities',
            self::Training => 'Training Programs',
            self::StatusUpdates => 'Application Status Updates',
            self::Deadlines => 'Application Deadlines',
            self::Announcements => 'Important Announcements',
        };
    }

    /**
     * Topics selected for every new account regardless of classification.
     *
     * @return list<self>
     */
    public static function alwaysSelected(): array
    {
        return [self::StatusUpdates];
    }
}
