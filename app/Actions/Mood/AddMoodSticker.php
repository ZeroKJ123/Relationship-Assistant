<?php

declare(strict_types=1);

namespace App\Actions\Mood;

use App\Enums\MoodStickerFormat;
use App\Enums\MoodStickerStatus;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AddMoodSticker
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, MoodEntry $entry, string $path, MoodStickerFormat $format): bool
    {
        return DB::transaction(function () use ($actor, $couple, $entry, $path, $format): bool {
            $currentCouple = $this->context->lockCouple($actor, $couple);
            $current = MoodEntry::query()->whereKey($entry->id)->where('couple_id', $currentCouple->id)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            if ($current->sticker_path === $path && $current->sticker_status === MoodStickerStatus::Ready) {
                return true;
            }
            if ($current->sticker_status !== MoodStickerStatus::Pending || $current->emoji !== null || MoodEntry::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->where('id', '>', $current->id)->exists()) {
                return false;
            }
            $current->forceFill(['sticker_path' => $path, 'sticker_format' => $format, 'sticker_status' => MoodStickerStatus::Ready])->save();
            DB::afterCommit(fn () => Log::info('mood.sticker_added', ['entry_id' => $current->id, 'actor_id' => $actor->id]));

            return true;
        }, attempts: 3);
    }
}
