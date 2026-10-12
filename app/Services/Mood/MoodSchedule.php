<?php

declare(strict_types=1);

namespace App\Services\Mood;

use App\Models\CoupleMember;
use App\Models\MoodPrompt;
use Carbon\CarbonImmutable;

final class MoodSchedule
{
    /** @return list<string> время вопросов участника в формате ЧЧ:ММ по часовому поясу пары */
    public function getTimes(CoupleMember $member): array
    {
        $times = $member->mood_schedule_times;

        return $times === null || $times === [] ? MoodPrompt::DEFAULT_TIMES : $times;
    }

    /** Последний наступивший сегодня вопрос, на который ещё можно ответить. */
    public function getLatestSlot(CoupleMember $member, CarbonImmutable $now, string $timezone): ?CarbonImmutable
    {
        $day = $now->setTimezone($timezone)->startOfDay();
        $latest = null;
        foreach ($this->getTimes($member) as $time) {
            $candidate = $day->setTimeFromTimeString($time)->utc();
            if ($candidate->lessThanOrEqualTo($now) && ($latest === null || $candidate->greaterThan($latest))) {
                $latest = $candidate;
            }
        }
        if ($latest === null || $latest->addHours(MoodPrompt::RESPONSE_WINDOW_HOURS)->lessThanOrEqualTo($now)) {
            return null;
        }

        return $latest;
    }

    public function isScheduled(CoupleMember $member, CarbonImmutable $moment, string $timezone): bool
    {
        $local = $moment->setTimezone($timezone);

        return $local->second === 0 && in_array($local->format(MoodPrompt::TIME_FORMAT), $this->getTimes($member), true);
    }
}
