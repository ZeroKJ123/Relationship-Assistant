<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\DutyStatus;
use App\Models\Duty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class DutyGroups
{
    private const GROUP_OVERDUE = 'overdue';

    private const GROUP_TODAY = 'today';

    private const GROUP_WEEK = 'week';

    private const GROUP_LATER = 'later';

    private const DAYS_AHEAD_IN_WEEK_GROUP = 7;

    /**
     * @param  Collection<int,Duty>  $duties
     * @return list<array{label:string,isAlert:bool,duties:Collection<int,Duty>}>
     */
    public function group(Collection $duties, CarbonImmutable $now): array
    {
        $labels = [
            self::GROUP_OVERDUE => __('Просрочено'), self::GROUP_TODAY => __('Сегодня'), self::GROUP_WEEK => __('Ближайшие 7 дней'), self::GROUP_LATER => __('Позже'),
            DutyStatus::Reported->value => __('Ждут проверки'), DutyStatus::Disputed->value => __('Оспорено'), DutyStatus::Confirmed->value => __('Завершено'), DutyStatus::Cancelled->value => __('Отменено'),
        ];
        $grouped = $duties->groupBy(fn (Duty $duty): string => $this->getGroup($duty, $now));
        $groups = [];
        foreach ($labels as $key => $label) {
            $items = $grouped->get($key);
            if ($items !== null) {
                $groups[] = ['label' => $label, 'isAlert' => in_array($key, [self::GROUP_OVERDUE, DutyStatus::Disputed->value], true), 'duties' => $items->values()];
            }
        }

        return $groups;
    }

    private function getGroup(Duty $duty, CarbonImmutable $now): string
    {
        if ($duty->status === DutyStatus::Overdue) {
            return self::GROUP_OVERDUE;
        }
        if ($duty->status !== DutyStatus::Pending) {
            return $duty->status->value;
        }
        $today = $now->setTimezone($duty->timezone)->endOfDay();
        $dueAt = $duty->due_at->setTimezone($duty->timezone);

        return match (true) {
            $dueAt->lessThanOrEqualTo($today) => self::GROUP_TODAY,
            $dueAt->lessThanOrEqualTo($today->addDays(self::DAYS_AHEAD_IN_WEEK_GROUP)) => self::GROUP_WEEK,
            default => self::GROUP_LATER,
        };
    }
}
