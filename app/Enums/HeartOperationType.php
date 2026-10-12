<?php

declare(strict_types=1);

namespace App\Enums;

enum HeartOperationType: string
{
    case AdminAdjustment = 'admin_adjustment';

    case BalanceReconciliation = 'balance_reconciliation';

    case ServicePayment = 'service_payment';

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
            self::AdminAdjustment => 'Изменение администратором',
            self::BalanceReconciliation => 'Сверка тестового баланса',
            self::ServicePayment => 'Оплата услуги сердечками',
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
