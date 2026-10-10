<?php

declare(strict_types=1);

namespace App\Actions\Mood;

use App\Enums\NotificationKind;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Requests\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class RequestPartnerMood
{
    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(private readonly RequestContext $context, private readonly NotificationOutbox $outbox) {}

    public function execute(User $actor, Couple $couple): MoodPrompt
    {
        return DB::transaction(function () use ($actor, $couple): MoodPrompt {
            $current = $this->context->lockCouple($actor, $couple);
            $recipientId = $current->members()->where('user_id', '!=', $actor->id)->sole()->user_id;
            $latestMoodAt = MoodEntry::query()->where('couple_id', $current->id)->where('user_id', $recipientId)->latest('recorded_at')->value('recorded_at');
            $pending = MoodPrompt::query()->where('couple_id', $current->id)->where('user_id', $recipientId)->where('is_manual', true)->whereNull('answered_at')->where('expires_at', '>', now());
            if ($latestMoodAt !== null) {
                $pending->where('scheduled_at', '>', $latestMoodAt);
            }
            $existing = $pending->latest('id')->lockForUpdate()->first();
            if ($existing !== null && $existing->scheduled_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isFuture()) {
                DB::afterCommit(fn () => Log::info('mood.partner_prompt_reused', ['prompt_id' => $existing->id, 'actor_id' => $actor->id]));

                return $existing;
            }
            $scheduledAt = CarbonImmutable::now()->setMicrosecond(0)->utc();
            if (($latestMoodAt !== null && $latestMoodAt->greaterThanOrEqualTo($scheduledAt)) || MoodPrompt::query()->where('couple_id', $current->id)->where('user_id', $recipientId)->where('scheduled_at', $scheduledAt)->exists()) {
                throw ValidationException::withMessages(['mood' => __('Запрос уже отправлен. Подождите секунду перед новым запросом.')]);
            }
            $expiresAt = $scheduledAt->addHours(MoodPrompt::RESPONSE_WINDOW_HOURS);
            if ($existing !== null) {
                $existing->forceFill(['expires_at' => $scheduledAt])->save();
            }
            $prompt = new MoodPrompt;
            $prompt->forceFill(['couple_id' => $current->id, 'user_id' => $recipientId, 'is_manual' => true, 'scheduled_at' => $scheduledAt, 'expires_at' => $expiresAt])->save();
            $this->outbox->record($current->id, $recipientId, NotificationKind::MoodPrompt, $prompt->id, expiresAt: $expiresAt);
            DB::afterCommit(fn () => Log::info('mood.partner_prompt_created', ['prompt_id' => $prompt->id, 'actor_id' => $actor->id, 'couple_id' => $current->id]));

            return $prompt;
        }, attempts: 3);
    }
}
