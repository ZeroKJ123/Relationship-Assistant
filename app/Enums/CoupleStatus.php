<?php

declare(strict_types=1);

namespace App\Enums;

enum CoupleStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ожидает партнёра',
            self::Active => 'Вы вместе',
            self::Ended => 'Пара завершена',
        };
    }
}
