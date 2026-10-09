<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Held = 'held';
    case Settled = 'settled';
    case Released = 'released';
}
