<?php

declare(strict_types=1);

namespace App\Enums;

enum Currency: string
{
    case Byn = 'BYN';
    case Rub = 'RUB';
    case Usd = 'USD';
    case Eur = 'EUR';
}
