<?php

declare(strict_types=1);

namespace App\Actions\Mood;

use App\Models\Couple;
use App\Models\MoodPrompt;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class SetMoodSchedule
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, bool $enabled, int $version): void
    {
        DB::transaction(function () use ($actor, $couple, $enabled, $version): void {
            $current = $this->context->lockCouple($actor, $couple);
            $member = $current->members()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            if ($member->mood_schedule_enabled === $enabled) {
                return;
            }
            if ($member->mood_schedule_version !== $version) {
                Log::warning('mood.schedule_denied', ['actor_id' => $actor->id, 'couple_id' => $current->id, 'reason' => 'stale_version']);
                throw ValidationException::withMessages(['expected_version' => __('Настройка изменилась. Обновите страницу.')]);
            }
            $member->forceFill(['mood_schedule_enabled' => $enabled, 'mood_schedule_version' => $version + 1])->save();
            if (! $enabled) {
                MoodPrompt::query()->where('couple_id', $current->id)->where('user_id', $actor->id)->where('is_manual', false)->whereNull('answered_at')->where('expires_at', '>', now())->update(['expires_at' => now()]);
            }
            DB::afterCommit(fn () => Log::info('mood.schedule_changed', ['actor_id' => $actor->id, 'couple_id' => $current->id, 'version' => $member->mood_schedule_version]));
        }, attempts: 3);
    }
}
