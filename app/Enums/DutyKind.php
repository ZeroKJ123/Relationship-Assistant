<?php

declare(strict_types=1);

namespace App\Enums;

enum DutyKind: string
{
    case Service = 'service';
    case Condition = 'condition';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Просьба',
            self::Condition => 'Встречное условие',
        };
    }
}
