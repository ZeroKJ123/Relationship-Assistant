<?php

declare(strict_types=1);

namespace App\Actions\Mood;

use App\Enums\NotificationKind;
use App\Models\Couple;
use App\Models\MoodPrompt;
use App\Models\User;
use App\Services\Mood\MoodSchedule;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Requests\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreateMoodPrompt
{
    public function __construct(private readonly RequestContext $context, private readonly NotificationOutbox $outbox, private readonly MoodSchedule $schedule) {}

    public function execute(User $actor, Couple $couple, CarbonImmutable $scheduledAt): ?MoodPrompt
    {
        return DB::transaction(function () use ($actor, $couple, $scheduledAt): ?MoodPrompt {
            $current = $this->context->lockCouple($actor, $couple);
            $member = $current->members()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            if (! $member->mood_schedule_enabled || ! $this->schedule->isScheduled($member, $scheduledAt, $current->timezone) || $scheduledAt->lessThan($member->joined_at) || $scheduledAt->isFuture()) {
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
            $this->outbox->record($current->id, $actor->id, NotificationKind::MoodPrompt, $prompt->id, expiresAt: $expiresAt);
            DB::afterCommit(fn () => Log::info('mood.prompt_created', ['prompt_id' => $prompt->id, 'actor_id' => $actor->id]));

            return $prompt;
        }, attempts: 3);
    }
}
