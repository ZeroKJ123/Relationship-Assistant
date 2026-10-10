<?php

declare(strict_types=1);

namespace App\Actions\Mood;

use App\Models\Couple;
use App\Models\MoodPrompt;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreateMoodPrompt
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, CarbonImmutable $scheduledAt): ?MoodPrompt
    {
        return DB::transaction(function () use ($actor, $couple, $scheduledAt): ?MoodPrompt {
            $current = $this->context->lockCouple($actor, $couple);
            $member = $current->members()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            $local = $scheduledAt->setTimezone($current->timezone);
            if (! $member->mood_schedule_enabled || ! in_array($local->hour, MoodPrompt::HOURS, true) || $local->minute !== 0 || $local->second !== 0 || $scheduledAt->isFuture()) {
                return null;
            }
            $expiresAt = $scheduledAt->setMicrosecond(0)->utc()->addHours(MoodPrompt::RESPONSE_WINDOW_HOURS);
            if ($expiresAt->lessThanOrEqualTo(now())) {
                return null;
            }
            $existing = MoodPrompt::query()->where('couple_id', $current->id)->where('user_id', $actor->id)->where('scheduled_at', $scheduledAt->setMicrosecond(0)->utc())->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            $prompt = new MoodPrompt;
            $prompt->forceFill(['couple_id' => $current->id, 'user_id' => $actor->id, 'scheduled_at' => $scheduledAt->setMicrosecond(0)->utc(), 'expires_at' => $expiresAt])->save();
            DB::afterCommit(fn () => Log::info('mood.prompt_created', ['prompt_id' => $prompt->id, 'actor_id' => $actor->id]));

            return $prompt;
        }, attempts: 3);
    }
}
