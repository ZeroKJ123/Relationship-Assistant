<?php

declare(strict_types=1);

namespace App\Enums;

enum ReminderStatus: string
{
    case Scheduled = 'scheduled';
    case Ready = 'ready';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Запланировано'), self::Ready => __('Ждёт доставки'), self::Cancelled => __('Отменено'), self::Expired => __('Срок прошёл'), self::Delivered => __('Доставлено'), self::Failed => __('Не доставлено')
        };
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Scheduled, self::Ready], true);
    }
}
