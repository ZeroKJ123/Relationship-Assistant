<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Enums\CoupleStatus;
use App\Enums\InvitationStatus;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\User;
use App\Services\Couples\CoupleLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class EndCouple
{
    public function __construct(private readonly CoupleLock $lock) {}

    public function execute(User $actor, Couple $couple): void
    {
        Gate::forUser($actor)->authorize('view', $couple);
        $userIds = array_values($couple->members()->orderBy('user_id')->get()->map(fn (CoupleMember $member): int => $member->user_id)->all());
        DB::transaction(function () use ($actor, $couple, $userIds): void {
            $this->lock->lockUsers($userIds);
            $current = $this->lock->lockCouple($couple->id);
            if ($current->status === CoupleStatus::Ended) {
                return;
            }
            Gate::forUser($actor)->authorize('update', $current);
            if ($current->members()->orderBy('user_id')->get()->map(fn (CoupleMember $member): int => $member->user_id)->values()->all() !== $userIds) {
                throw ValidationException::withMessages(['couple' => __('Состав пары изменился. Обновите страницу и подтвердите выход снова.')]);
            }
            $endedAt = now();
            $current->members()->whereNull('ended_at')->update(['active_user_id' => null, 'ended_at' => $endedAt]);
            $current->invitations()->where('status', InvitationStatus::Pending->value)->update(['status' => InvitationStatus::Cancelled->value]);
            $current->forceFill(['status' => CoupleStatus::Ended, 'ended_at' => $endedAt])->save();
        });
        Log::info('couple.ended', ['actor_id' => $actor->id, 'couple_id' => $couple->id]);
    }
}
