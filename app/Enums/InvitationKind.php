<?php

declare(strict_types=1);

namespace App\Enums;

enum InvitationKind: string
{
    case Date = 'date';
    case Activity = 'activity';

    public function label(): string
    {
        return match ($this) {
            self::Date => 'Свидание', self::Activity => 'Совместное мероприятие'
        };
    }
}
