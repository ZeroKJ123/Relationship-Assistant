<?php

declare(strict_types=1);

namespace App\Services\Mood;

use App\Enums\MoodLevel;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class MoodCalendar
{
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * Последняя оценка пользователя за каждый день месяца по времени пары.
     * Без выбранной даты показывается текущий месяц.
     *
     * @return array{title:string,offset:int,days:list<array{day:int,date:string,level:?MoodLevel,isToday:bool,isSelected:bool}>,marked:int,hard:int,frequent:?MoodLevel,previous:string,next:?string}
     */
    public function getMonth(Couple $couple, User $user, CarbonImmutable $now, ?string $selectedDate = null): array
    {
        $today = $now->setTimezone($couple->timezone);
        $selected = $selectedDate === null ? null : CarbonImmutable::parse($selectedDate, $couple->timezone)->startOfDay();
        $start = ($selected ?? $today)->startOfMonth();
        $levels = [];
        $entries = MoodEntry::query()->where('couple_id', $couple->id)->where('user_id', $user->id)->whereBetween('recorded_at', [$start->utc(), $start->endOfMonth()->utc()])->orderBy('recorded_at')->orderBy('id')->get(['id', 'level', 'recorded_at']);
        foreach ($entries as $entry) {
            $levels[$entry->recorded_at->setTimezone($couple->timezone)->day] = $entry->level;
        }
        $days = [];
        foreach (range(1, $start->daysInMonth) as $day) {
            $date = $start->setDay($day);
            $days[] = ['day' => $day, 'date' => $date->format(self::DATE_FORMAT), 'level' => $levels[$day] ?? null, 'isToday' => $date->isSameDay($today), 'isSelected' => $selected !== null && $date->isSameDay($selected)];
        }
        $counts = array_count_values(array_map(fn (MoodLevel $level): string => $level->value, $levels));
        arsort($counts);
        $frequent = array_key_first($counts);
        $nextMonth = $start->addMonth();

        return [
            'title' => Str::ucfirst($start->isoFormat('MMMM YYYY')),
            'offset' => $start->isoWeekday() - 1,
            'days' => $days,
            'marked' => count($levels),
            'hard' => count(array_filter($levels, fn (MoodLevel $level): bool => in_array($level, [MoodLevel::Low, MoodLevel::Bad], true))),
            'frequent' => $frequent === null ? null : MoodLevel::from($frequent),
            'previous' => $start->subMonth()->format(self::DATE_FORMAT),
            'next' => $nextMonth->greaterThan($today) ? null : $nextMonth->format(self::DATE_FORMAT),
        ];
    }
}
