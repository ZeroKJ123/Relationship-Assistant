<?php

declare(strict_types=1);

namespace App\Enums;

enum MoodLevel: string
{
    case Great = 'great';
    case Good = 'good';
    case Neutral = 'neutral';
    case Low = 'low';
    case Bad = 'bad';

    public function label(): string
    {
        return match ($this) {
            self::Great => __('Отлично'), self::Good => __('Хорошо'), self::Neutral => __('Нейтрально'), self::Low => __('Не очень'), self::Bad => __('Плохо')
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Great => 'mood-great', self::Good => 'mood-good', self::Neutral => 'mood-neutral', self::Low => 'mood-low', self::Bad => 'mood-bad'
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Great => '😄', self::Good => '🙂', self::Neutral => '😐', self::Low => '🙁', self::Bad => '😞'
        };
    }
}
