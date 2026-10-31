<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SkillType: string
{
    use HasOptions;

    case Technical = 'technical';
    case Soft = 'soft';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Technical skill',
            self::Soft => 'Soft skill',
        };
    }
}
