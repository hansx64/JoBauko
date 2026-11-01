<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TrainingType: string
{
    use HasOptions;

    case SkillsTraining = 'skills_training';
    case Seminar = 'seminar';
    case Workshop = 'workshop';
    case Orientation = 'orientation';

    public function label(): string
    {
        return match ($this) {
            self::SkillsTraining => 'Skills training',
            self::Seminar => 'Seminar',
            self::Workshop => 'Workshop',
            self::Orientation => 'Orientation / briefing',
        };
    }
}
