<?php

declare(strict_types=1);

namespace App\Services\Duties;

use App\Enums\NotificationKind;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\DutyConfirmation;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class DutyContext
{
    public function __construct(private readonly RequestContext $requests, private readonly NotificationOutbox $outbox) {}

    public function lock(User $actor, Couple $couple, Duty $duty, string $ability): Duty
    {
        $currentCouple = $this->requests->lockCouple($actor, $couple);
        $current = Duty::query()->where('couple_id', $currentCouple->id)->whereKey($duty->id)->lockForUpdate()->firstOrFail();
        $current->setRelation('couple', $currentCouple);
        Gate::forUser($actor)->authorize($ability, $current);

        return $current;
    }

    public function isRepeated(Duty $duty, User $actor, int $version, string $stage, bool $answer): bool
    {
        if ($duty->version !== $version + 1) {
            return false;
        }
        $confirmation = $duty->confirmations()->where('version', $duty->version)->lockForUpdate()->first();

        return $confirmation !== null && $confirmation->actor_id === $actor->id && $confirmation->stage === $stage && $confirmation->answer === $answer;
    }

    public function requireVersion(Duty $duty, int $version): void
    {
        if ($duty->version !== $version) {
            $this->deny($duty, 'stale_version');
        }
    }

    public function deny(Duty $duty, string $reason): never
    {
        Log::warning('duty.transition_denied', ['duty_id' => $duty->id, 'version' => $duty->version, 'reason' => $reason]);
        throw ValidationException::withMessages(['duty' => __('Состояние дела изменилось. Обновите страницу и подтвердите актуальный ответ.')]);
    }

    public function record(Duty $duty, ?User $actor, string $stage, ?bool $answer): void
    {
        $event = new DutyConfirmation;
        $event->forceFill(['duty_id' => $duty->id, 'actor_id' => $actor?->id, 'stage' => $stage, 'answer' => $answer, 'version' => $duty->version])->save();
        $recipientIds = $actor === null ? [$duty->executor_id, $duty->reviewer_id] : [$actor->id === $duty->executor_id ? $duty->reviewer_id : $duty->executor_id];
        foreach ($recipientIds as $recipientId) {
            $this->outbox->record($duty->couple_id, $recipientId, NotificationKind::DutyEvent, $event->id);
        }
        DB::afterCommit(fn () => Log::info('duty.transitioned', ['duty_id' => $duty->id, 'actor_id' => $actor?->id, 'stage' => $stage, 'status' => $duty->status->value, 'version' => $duty->version, 'reported_at' => $duty->reported_at?->toIso8601String(), 'reviewed_at' => $duty->reviewed_at?->toIso8601String()]));
    }
}
