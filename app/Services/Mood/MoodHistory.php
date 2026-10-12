<?php

declare(strict_types=1);

namespace App\Services\Mood;

use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class MoodHistory
{
    /** @return Collection<int,MoodEntry> */
    public function getLatest(Couple $couple): Collection
    {
        $ids = MoodEntry::query()->selectRaw('MAX(id)')->where('couple_id', $couple->id)->groupBy('user_id');

        return MoodEntry::query()->whereIn('id', $ids)->with('user:id,name')->get()->keyBy('user_id');
    }

    public function getPendingPrompt(User $actor, Couple $couple): ?MoodPrompt
    {
        $isScheduleEnabled = $couple->members()->where('user_id', $actor->id)->where('active_user_id', $actor->id)->value('mood_schedule_enabled') === true;
        $latestMood = MoodEntry::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->latest('recorded_at')->first();
        $prompts = MoodPrompt::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->whereNull('answered_at')->where('expires_at', '>', now())->where('scheduled_at', '<=', now());
        if (! $isScheduleEnabled) {
            $prompts->where('is_manual', true);
        }
        if ($latestMood !== null) {
            $prompts->where('scheduled_at', '>', $latestMood->recorded_at);
        }

        return $prompts->latest('id')->first();
    }
}
