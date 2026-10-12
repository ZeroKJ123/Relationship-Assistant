<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Data\Couples\StreakState;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\StreakRestore;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Серия пары: день засчитан, когда оба партнёра отметили настроение, либо день восстановлен. */
final class CoupleStreak
{
    public const RESTORES_PER_MONTH = 5;

    /** Минимальная длина серии для каждого уровня огонька, начиная с первого. */
    public const LEVEL_DAYS = [1, 3, 7, 30, 100];

    private const WINDOW_DAYS = 800;

    private const DATE_FORMAT = 'Y-m-d';

    public function get(Couple $couple, User $actor, CarbonImmutable $now): StreakState
    {
        $today = $now->setTimezone($couple->timezone)->startOfDay();
        $memberIds = $couple->members()->whereNotNull('active_user_id')->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $marks = [];
        $entries = MoodEntry::query()->where('couple_id', $couple->id)->whereIn('user_id', $memberIds)->where('recorded_at', '>=', $today->subDays(self::WINDOW_DAYS)->utc())->get(['id', 'user_id', 'recorded_at']);
        foreach ($entries as $entry) {
            $marks[$entry->recorded_at->setTimezone($couple->timezone)->format(self::DATE_FORMAT)][$entry->user_id] = true;
        }
        $restores = StreakRestore::query()->where('couple_id', $couple->id)->get(['id', 'restored_on', 'created_at']);
        $restored = [];
        foreach ($restores as $restore) {
            $restored[$restore->restored_on->format(self::DATE_FORMAT)] = true;
        }
        $isComplete = fn (CarbonImmutable $day): bool => isset($restored[$day->format(self::DATE_FORMAT)]) || count($marks[$day->format(self::DATE_FORMAT)] ?? []) >= max(2, count($memberIds));
        $run = function (CarbonImmutable $end) use ($isComplete): int {
            $length = 0;
            while ($length < self::WINDOW_DAYS && $isComplete($end->subDays($length))) {
                $length++;
            }

            return $length;
        };
        $isTodayComplete = $isComplete($today);
        $days = $isTodayComplete ? $run($today) : $run($today->subDay());
        $monthStart = $today->startOfMonth()->utc();
        $restoresLeft = max(0, self::RESTORES_PER_MONTH - $restores->filter(fn (StreakRestore $restore): bool => $restore->created_at !== null && $restore->created_at->greaterThanOrEqualTo($monthStart))->count());
        $gapEnd = ($isTodayComplete ? $today : $today->subDay())->subDays($days);
        $missed = [];
        while (count($missed) <= $restoresLeft && ! $isComplete($gapEnd->subDays(count($missed)))) {
            $missed[] = $gapEnd->subDays(count($missed))->format(self::DATE_FORMAT);
        }
        $previous = $run($gapEnd->subDays(count($missed)));
        $canRestore = $missed !== [] && count($missed) <= $restoresLeft && $previous > 0;
        $todayMarks = $marks[$today->format(self::DATE_FORMAT)] ?? [];

        return new StreakState(
            days: $days,
            level: $this->getLevel($days),
            isTodayComplete: $isTodayComplete,
            hasActorMarked: isset($todayMarks[$actor->id]),
            hasPartnerMarked: array_diff(array_keys($todayMarks), [$actor->id]) !== [],
            restoresLeft: $restoresLeft,
            missedDays: $canRestore ? $missed : [],
            restoredDays: $canRestore ? $days + count($missed) + $previous : 0,
        );
    }

    public function getLevel(int $days): int
    {
        $level = 0;
        foreach (self::LEVEL_DAYS as $index => $minimum) {
            if ($days >= $minimum) {
                $level = $index + 1;
            }
        }

        return $level;
    }
}
