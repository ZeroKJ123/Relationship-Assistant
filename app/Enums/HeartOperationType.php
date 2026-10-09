<?php

declare(strict_types=1);

namespace App\Enums;

enum HeartOperationType: string
{
    case StartingBonus = 'starting_bonus';
    case Reward = 'reward';
    case RefusalPenalty = 'refusal_penalty';
    case OverduePenalty = 'overdue_penalty';
    case FalseReportPenalty = 'false_report_penalty';
    case ManualThanks = 'manual_thanks';
    case ManualDeduction = 'manual_deduction';

    public function label(): string
    {
        return match ($this) {
            self::StartingBonus => 'Стартовые сердечки',
            self::Reward => 'Подтверждённое выполнение',
            self::RefusalPenalty => 'Отказ от «Очень хочу»',
            self::OverduePenalty => 'Просрочка',
            self::FalseReportPenalty => 'Оспоренная отметка',
            self::ManualThanks => 'Благодарность партнёра',
            self::ManualDeduction => 'Списание партнёром',
        };
    }
}
