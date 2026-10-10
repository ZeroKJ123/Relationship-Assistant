<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramLoginStatus: string
{
    case Pending = 'pending';
    case Identified = 'identified';
    case Confirmed = 'confirmed';
    case Consumed = 'consumed';
    case Cancelled = 'cancelled';
}
