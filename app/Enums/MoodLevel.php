<?php

declare(strict_types=1);

namespace App\Enums;

enum MoodLevel: string
{
    case Great = 'great';
    case Good = 'good';
    case Low = 'low';
    case Bad = 'bad';

    public function label(): string
    {
        return match ($this) {
            self::Great => __('Отлично'), self::Good => __('Хорошо'), self::Low => __('Не очень'), self::Bad => __('Плохо')
        };
    }
}
