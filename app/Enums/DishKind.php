<?php

declare(strict_types=1);

namespace App\Enums;

enum DishKind: string
{
    case Ordinary = 'ordinary';
    case Special = 'special';

    public function label(): string
    {
        return match ($this) {
            self::Ordinary => __('Обычное'), self::Special => __('Особое')
        };
    }
}
