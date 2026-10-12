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
    /**
     * Последняя оценка пользователя за каждый день текущего месяца по времени пары.
     *
     * @return array{title:string,offset:int,days:list<array{day:int,level:?MoodLevel,isToday:bool}>,marked:int,hard:int,frequent:?MoodLevel}
     */
    public function getMonth(Couple $couple, User $user, CarbonImmutable $now): array
    {
        $today = $now->setTimezone($couple->timezone);
        $start = $today->startOfMonth();
        $levels = [];
        $entries = MoodEntry::query()->where('couple_id', $couple->id)->where('user_id', $user->id)->whereBetween('recorded_at', [$start->utc(), $today->endOfMonth()->utc()])->orderBy('recorded_at')->orderBy('id')->get(['id', 'level', 'recorded_at']);
        foreach ($entries as $entry) {
            $levels[$entry->recorded_at->setTimezone($couple->timezone)->day] = $entry->level;
        }
        $days = [];
        foreach (range(1, $start->daysInMonth) as $day) {
            $days[] = ['day' => $day, 'level' => $levels[$day] ?? null, 'isToday' => $day === $today->day];
        }
        $counts = array_count_values(array_map(fn (MoodLevel $level): string => $level->value, $levels));
        arsort($counts);
        $frequent = array_key_first($counts);

        return [
            'title' => Str::ucfirst($start->isoFormat('MMMM YYYY')),
            'offset' => $start->isoWeekday() - 1,
            'days' => $days,
            'marked' => count($levels),
            'hard' => count(array_filter($levels, fn (MoodLevel $level): bool => in_array($level, [MoodLevel::Low, MoodLevel::Bad], true))),
            'frequent' => $frequent === null ? null : MoodLevel::from($frequent),
        ];
    }
}
