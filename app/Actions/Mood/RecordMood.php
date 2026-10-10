<?php

declare(strict_types=1);

namespace App\Actions\Mood;

use App\Data\Mood\MoodData;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RecordMood
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, MoodData $data): MoodEntry
    {
        Validator::make(['operation_key' => $data->operationKey], ['operation_key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($actor, $couple, $data): MoodEntry {
            $current = $this->context->lockCouple($actor, $couple);
            $existing = MoodEntry::query()->where('user_id', $actor->id)->where('operation_key', $data->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->couple_id !== $current->id || $existing->level !== $data->level || $existing->source !== $data->source || $existing->mood_prompt_id !== $data->promptId) {
                    $this->deny('changed_payload');
                }

                return $existing;
            }
            if ($data->promptId !== null) {
                $this->answerPrompt($actor, $current, $data->promptId);
            }
            $entry = new MoodEntry;
            $entry->forceFill(['couple_id' => $current->id, 'user_id' => $actor->id, 'level' => $data->level, 'source' => $data->source, 'mood_prompt_id' => $data->promptId, 'operation_key' => $data->operationKey, 'recorded_at' => now()])->save();
            DB::afterCommit(fn () => Log::info('mood.recorded', ['entry_id' => $entry->id, 'actor_id' => $actor->id, 'couple_id' => $current->id, 'source' => $data->source->value]));

            return $entry;
        }, attempts: 3);
    }

    private function answerPrompt(User $actor, Couple $couple, int $id): void
    {
        $prompt = MoodPrompt::query()->whereKey($id)->where('couple_id', $couple->id)->where('user_id', $actor->id)->lockForUpdate()->first();
        $member = $couple->members()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
        if ($prompt === null || $prompt->answered_at !== null || $prompt->scheduled_at->isFuture() || $prompt->expires_at->lessThanOrEqualTo(now()) || ! $member->mood_schedule_enabled) {
            $this->deny('unavailable_prompt');
        }
        if (MoodEntry::query()->where('couple_id', $couple->id)->where('user_id', $actor->id)->where('recorded_at', '>=', $prompt->scheduled_at)->lockForUpdate()->exists()) {
            $this->deny('outdated_prompt');
        }
        $prompt->forceFill(['answered_at' => now()])->save();
    }

    private function deny(string $reason): never
    {
        Log::warning('mood.denied', ['reason' => $reason]);
        throw ValidationException::withMessages(['mood' => __('Оценка уже сохранена или запрос устарел. Обновите страницу и отметьте настроение заново.')]);
    }
}
