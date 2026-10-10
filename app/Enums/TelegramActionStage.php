<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramActionStage: string
{
    case Ready = 'ready';
    case Awaiting = 'awaiting';
    case Confirm = 'confirm';
    case Consumed = 'consumed';
    case Cancelled = 'cancelled';
}
