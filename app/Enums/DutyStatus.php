<?php

declare(strict_types=1);

namespace App\Enums;

enum DutyStatus: string
{
    case Cancelled = 'cancelled';

    case Pending = 'pending';
    case Reported = 'reported';
    case Confirmed = 'confirmed';
    case Disputed = 'disputed';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Cancelled => 'Отменено',
            self::Pending => 'Нужно выполнить',
            self::Reported => 'Ждём проверки',
            self::Confirmed => 'Выполнение подтверждено',
            self::Disputed => 'Отметка оспорена',
            self::Overdue => 'Срок прошёл',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Overdue, self::Disputed => 'danger',
            self::Reported => 'warning',
            self::Confirmed => 'success',
            self::Pending, self::Cancelled => 'neutral',
        };
    }

    /** Номер текущего шага на шкале «создано → выполнение → проверка»; 0 — дело отменено. */
    public function progressStep(): int
    {
        return match ($this) {
            self::Cancelled => 0,
            self::Pending, self::Overdue => 2,
            self::Reported, self::Disputed => 3,
            self::Confirmed => 4,
        };
    }
}
