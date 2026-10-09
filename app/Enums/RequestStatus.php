<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestStatus: string
{
    case Cancelled = 'cancelled';

    case Pending = 'pending';
    case Conditional = 'conditional';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Cancelled => 'Отменено',
            self::Pending => 'Ждём ответа',
            self::Conditional => 'Ждём принятия условия',
            self::Accepted => 'Договорились',
            self::Declined => 'Просьба отклонена',
        };
    }
}
