<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Data\Couples\UpcomingDate;
use App\Models\Couple;
use App\Models\MemorableDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MemorableDates
{
    public const MAXIMUM_PER_COUPLE = 50;

    public const KEY_STARTED = 'started';

    public const KEY_MARRIED = 'married';

    /**
     * Ближайшие даты пары по её часовому поясу: годовщины отношений и свадьбы и добавленные даты.
     * Прошедшие разовые даты не показываются.
     *
     * @return Collection<int,UpcomingDate>
     */
    public function getUpcoming(Couple $couple, CarbonImmutable $now): Collection
    {
        $today = $now->setTimezone($couple->timezone)->startOfDay();
        $dates = collect([$this->getOccurrence(self::KEY_STARTED, __('Годовщина отношений'), $couple->started_on, true, $today)]);
        if ($couple->married_on !== null) {
            $dates->push($this->getOccurrence(self::KEY_MARRIED, __('Годовщина свадьбы'), $couple->married_on, true, $today));
        }
        foreach (MemorableDate::query()->where('couple_id', $couple->id)->orderBy('id')->limit(self::MAXIMUM_PER_COUPLE)->get() as $date) {
            $dates->push($this->getOccurrence(sprintf('date:%d', $date->id), $date->title, $date->occurs_on, $date->is_yearly, $today, $date->id));
        }

        return $dates->filter()->sortBy(fn (UpcomingDate $date): int => $date->daysLeft)->values();
    }

    private function getOccurrence(string $key, string $title, CarbonImmutable $origin, bool $isYearly, CarbonImmutable $today, ?int $id = null): ?UpcomingDate
    {
        $date = CarbonImmutable::create($origin->year, $origin->month, $origin->day, timezone: $today->timezone);
        if ($date === null) {
            return null;
        }
        if (! $isYearly) {
            return $date->lessThan($today) ? null : new UpcomingDate($key, $title, $date, (int) $today->diffInDays($date), id: $id);
        }
        $next = $this->inYear($date, max($today->year, $date->year));
        if ($next->lessThan($today)) {
            $next = $this->inYear($date, $today->year + 1);
        }
        $years = $next->year - $date->year;

        return new UpcomingDate($key, $title, $next, (int) $today->diffInDays($next), $years > 0 ? $years : null, $id);
    }

    /** 29 февраля в невисокосный год отмечается 28 февраля. */
    private function inYear(CarbonImmutable $date, int $year): CarbonImmutable
    {
        $month = $date->setDate($year, $date->month, 1);

        return $month->setDay(min($date->day, $month->daysInMonth));
    }
}
