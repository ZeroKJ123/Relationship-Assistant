<?php

declare(strict_types=1);

namespace App\Enums;

enum ConditionType: string
{
    case Action = 'action';
    case Kisses = 'kisses';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Action => 'Действие',
            self::Kisses => 'Поцелуи',
            self::Custom => 'Своё условие',
        };
    }
}
