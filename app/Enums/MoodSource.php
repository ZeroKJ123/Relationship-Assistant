<?php

declare(strict_types=1);

namespace App\Enums;

enum MoodSource: string
{
    case Web = 'web';
    case Telegram = 'telegram';
}
