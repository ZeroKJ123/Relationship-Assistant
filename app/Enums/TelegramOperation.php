<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramOperation: string
{
    case Agree = 'agree';
    case Decline = 'decline';
    case NoFoodMoney = 'no_food_money';
    case AcceptCondition = 'accept_condition';
    case DeclineCondition = 'decline_condition';
    case Done = 'done';
    case NotDone = 'not_done';
    case Verify = 'verify';
    case Dispute = 'dispute';
    case MoodGreat = 'mood_great';
    case MoodGood = 'mood_good';
    case MoodLow = 'mood_low';
    case MoodBad = 'mood_bad';

    public function getSourceKind(): string
    {
        return match ($this) {
            self::Agree, self::Decline, self::NoFoodMoney, self::AcceptCondition, self::DeclineCondition => 'request',
            self::Done, self::NotDone, self::Verify, self::Dispute => 'duty',
            default => 'mood',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Agree => __('Согласиться'), self::Decline => __('Отказаться'),
            self::NoFoodMoney => __('Нет денег на продукты'),
            self::AcceptCondition => __('Принять условие'), self::DeclineCondition => __('Отклонить условие'),
            self::Done => __('Сделано'), self::NotDone => __('Не сделано'),
            self::Verify => __('Подтвердить выполнение'), self::Dispute => __('Оспорить выполнение'),
            self::MoodGreat => __('Отлично'), self::MoodGood => __('Хорошо'),
            self::MoodLow => __('Не очень'), self::MoodBad => __('Плохо'),
        };
    }
}
