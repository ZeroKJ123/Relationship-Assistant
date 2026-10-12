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
    case MoodNeutral = 'mood_neutral';
    case MoodLow = 'mood_low';
    case MoodBad = 'mood_bad';
    case MoodEmoji = 'mood_emoji';
    case MoodEmojiSkip = 'mood_emoji_skip';

    public function getSourceKind(): string
    {
        return match ($this) {
            self::Agree, self::Decline, self::NoFoodMoney, self::AcceptCondition, self::DeclineCondition => 'request',
            self::Done, self::NotDone, self::Verify, self::Dispute => 'duty',
            self::MoodEmoji, self::MoodEmojiSkip => 'mood_emoji',
            default => 'mood',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Agree => __('✅ Согласиться'), self::Decline => __('✖️ Отказаться'),
            self::NoFoodMoney => __('Нет денег на продукты'),
            self::AcceptCondition => __('✅ Принять'), self::DeclineCondition => __('✖️ Отклонить'),
            self::Done => __('✅ Сделано'), self::NotDone => __('Пока нет'),
            self::Verify => __('✅ Подтверждаю'), self::Dispute => __('⚠️ Оспорить'),
            self::MoodGreat => MoodLevel::Great->emoji(), self::MoodGood => MoodLevel::Good->emoji(),
            self::MoodNeutral => MoodLevel::Neutral->emoji(),
            self::MoodLow => MoodLevel::Low->emoji(), self::MoodBad => MoodLevel::Bad->emoji(),
            self::MoodEmoji => __('Дополнить эмоджи'), self::MoodEmojiSkip => __('Пропустить'),
        };
    }
}
