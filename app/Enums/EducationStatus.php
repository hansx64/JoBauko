<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EducationStatus: string
{
    use HasOptions;

    case Studying = 'studying';
    case Completed = 'completed';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Studying => 'Currently studying',
            self::Completed => 'Finished my studies',
            self::Stopped => 'Stopped studying',
        };
    }
}
