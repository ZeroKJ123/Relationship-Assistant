<?php

declare(strict_types=1);

namespace App\Enums;

enum DutyStatus: string
{
    case Pending = 'pending';
    case Reported = 'reported';
    case Confirmed = 'confirmed';
    case Disputed = 'disputed';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Нужно выполнить',
            self::Reported => 'Ждём проверки',
            self::Confirmed => 'Выполнение подтверждено',
            self::Disputed => 'Отметка оспорена',
            self::Overdue => 'Срок прошёл',
        };
    }
}
