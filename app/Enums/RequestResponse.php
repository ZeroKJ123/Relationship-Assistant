<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestResponse: string
{
    case Agree = 'agree';
    case Decline = 'decline';
    case Conditional = 'conditional';

    public function label(): string
    {
        return match ($this) {
            self::Agree => 'Согласиться',
            self::Decline => 'Отказать',
            self::Conditional => 'Предложить условие',
        };
    }
}
