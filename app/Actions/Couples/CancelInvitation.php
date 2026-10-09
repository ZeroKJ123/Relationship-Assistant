<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Enums\InvitationStatus;
use App\Models\CoupleInvitation;
use App\Models\User;
use App\Services\Couples\CoupleLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CancelInvitation
{
    public function __construct(private readonly CoupleLock $lock) {}

    public function execute(User $actor, CoupleInvitation $invitation): void
    {
        Gate::forUser($actor)->authorize('cancel', $invitation);
        DB::transaction(function () use ($actor, $invitation): void {
            $this->lock->lockUsers([$actor->id]);
            $this->lock->lockCouple($invitation->couple_id);
            $current = CoupleInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('cancel', $current);
            if ($current->status === InvitationStatus::Cancelled) {
                return;
            }
            if ($current->status !== InvitationStatus::Pending) {
                throw ValidationException::withMessages(['invitation' => __('Принятое приглашение нельзя отменить. Завершите пару отдельно.')]);
            }
            $current->forceFill(['status' => InvitationStatus::Cancelled])->save();
        });
        Log::info('invitation.cancelled', ['actor_id' => $actor->id, 'invitation_id' => $invitation->id]);
    }
}
