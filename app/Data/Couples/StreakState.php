<?php

declare(strict_types=1);

namespace App\Data\Couples;

final readonly class StreakState
{
    /**
     * @param  int  $days  длина текущей серии
     * @param  int  $level  уровень огонька от 0 (серии нет) до 5
     * @param  list<string>  $missedDays  пропущенные дни, которые можно восстановить, в формате ГГГГ-ММ-ДД
     * @param  int  $restoredDays  длина серии после восстановления
     */
    public function __construct(public int $days, public int $level, public bool $isTodayComplete, public bool $hasActorMarked, public bool $hasPartnerMarked, public int $restoresLeft, public array $missedDays = [], public int $restoredDays = 0) {}

    public function canRestore(): bool
    {
        return $this->missedDays !== [];
    }

    public function getStatus(): string
    {
        return match (true) {
            $this->isTodayComplete => __('сегодня отмечено'),
            $this->hasActorMarked => __('ждём партнёра'),
            $this->hasPartnerMarked => __('ждём вас'),
            default => __('сегодня ещё нет'),
        };
    }
}
