<?php

declare(strict_types=1);

namespace App\Enums;

enum SelectionStatus: string
{
    case Waiting = 'waiting';
    case Active = 'active';
    case Matched = 'matched';
    case Exhausted = 'exhausted';
    case Declined = 'declined';
}
