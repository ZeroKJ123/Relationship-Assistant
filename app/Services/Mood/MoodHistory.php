<?php

declare(strict_types=1);

namespace App\Services\Mood;

use App\Models\Couple;
use App\Models\MoodEntry;
use Illuminate\Database\Eloquent\Collection;

final class MoodHistory
{
    /** @return Collection<int,MoodEntry> */
    public function getLatest(Couple $couple): Collection
    {
        $ids = MoodEntry::query()->selectRaw('MAX(id)')->where('couple_id', $couple->id)->where('recorded_at', '>=', now()->subDays(MoodEntry::HISTORY_DAYS))->groupBy('user_id');

        return MoodEntry::query()->whereIn('id', $ids)->with('user:id,name')->get()->keyBy('user_id');
    }
}
