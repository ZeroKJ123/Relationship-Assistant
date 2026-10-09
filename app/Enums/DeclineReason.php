<?php

declare(strict_types=1);

namespace App\Enums;

enum DeclineReason: string
{
    case Ordinary = 'ordinary';
    case NoFoodMoney = 'no_food_money';

    public function label(): string
    {
        return match ($this) {
            self::Ordinary => 'Не могу согласиться',
            self::NoFoodMoney => 'Нет денег на продукты',
        };
    }
}
