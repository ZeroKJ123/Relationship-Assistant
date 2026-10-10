<?php

declare(strict_types=1);

namespace App\Enums;

enum DutyFilter: string
{
    case Active = 'active';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активные',
            self::Waiting => 'На проверке',
            self::Completed => 'Завершённые',
            self::All => 'Все',
        };
    }

    /** @return list<DutyStatus> */
    public function statuses(): array
    {
        return match ($this) {
            self::Active => [DutyStatus::Pending, DutyStatus::Overdue],
            self::Waiting => [DutyStatus::Reported],
            self::Completed => [DutyStatus::Confirmed, DutyStatus::Disputed, DutyStatus::Cancelled],
            self::All => DutyStatus::cases(),
        };
    }
}
