<?php

declare(strict_types=1);

namespace App\Actions\Mood;

use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\User;
use App\Rules\SingleEmoji;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class AddMoodEmoji
{
    public const EMOJIS = ['😊', '🥰', '😍', '🥳', '😌', '😴', '🤔', '😢', '😤', '🤒', '💪', '❤️'];

    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, MoodEntry $entry, string $emoji): void
    {
        Validator::make(['emoji' => $emoji], ['emoji' => ['required', new SingleEmoji]])->validate();
        DB::transaction(function () use ($actor, $couple, $entry, $emoji): void {
            $currentCouple = $this->context->lockCouple($actor, $couple);
            $current = MoodEntry::query()->whereKey($entry->id)->where('couple_id', $currentCouple->id)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            if ($current->emoji === $emoji) {
                return;
            }
            if ($current->emoji !== null || $current->sticker_status !== null || MoodEntry::query()->where('couple_id', $currentCouple->id)->where('user_id', $actor->id)->where('id', '>', $current->id)->exists()) {
                $this->context->deny('outdated_mood');
            }
            $current->forceFill(['emoji' => $emoji])->save();
            DB::afterCommit(fn () => Log::info('mood.emoji_added', ['entry_id' => $current->id, 'actor_id' => $actor->id]));
        }, attempts: 3);
    }
}
