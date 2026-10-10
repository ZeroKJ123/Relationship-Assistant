<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestType: string
{
    case Rating = 'rating';

    case Gift = 'gift';

    case Service = 'service';
    case Food = 'food';
    case Guests = 'guests';
    case Event = 'event';
    case Money = 'money';

    public function label(): string
    {
        return match ($this) {
            self::Rating => 'Оценить поступок',
            self::Gift => 'Подарок',
            self::Service => 'Услуга',
            self::Food => 'Приготовление еды',
            self::Guests => 'Гости',
            self::Event => 'Совместное событие',
            self::Money => 'Денежная просьба',
        };
    }
}
