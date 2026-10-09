<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Enums\CoupleSlot;
use App\Enums\CoupleStatus;
use App\Enums\InvitationStatus;
use App\Models\Couple;
use App\Models\CoupleInvitation;
use App\Models\CoupleMember;
use App\Models\User;
use App\Services\Couples\CoupleLock;
use App\Services\Couples\InvitationAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AcceptInvitation
{
    public function __construct(private readonly CoupleLock $lock, private readonly InvitationAvailability $availability) {}

    public function execute(User $actor, string $token): Couple
    {
        $snapshot = CoupleInvitation::query()->where('token_hash', hash('sha256', $token))->firstOrFail();
        Log::debug('invitation.accept_started', ['actor_id' => $actor->id, 'invitation_id' => $snapshot->id]);
        $couple = DB::transaction(function () use ($actor, $snapshot): Couple {
            $this->lock->lockUsers([$actor->id, $snapshot->inviter_id]);
            $couple = $this->lock->lockCouple($snapshot->couple_id);
            $invitation = CoupleInvitation::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            if ($actor->id === $invitation->inviter_id) {
                $this->reject(__('Нельзя принять собственное приглашение.'), 'own_invitation');
            }
            if ($invitation->status === InvitationStatus::Accepted && $invitation->accepted_by_id === $actor->id) {
                return $couple;
            }
            if (! $this->availability->isAvailable($invitation, $couple)) {
                $this->reject(__('Приглашение больше не действует. Попросите новую ссылку.'), 'unavailable');
            }
            if (CoupleMember::query()->where('active_user_id', $actor->id)->exists()) {
                $this->reject(__('Сначала завершите текущую пару или своё ожидающее приглашение.'), 'actor_has_current_couple');
            }
            $hasAuthor = $couple->members()->where('user_id', $invitation->inviter_id)->where('active_user_id', $invitation->inviter_id)->where('slot', CoupleSlot::First->value)->exists();
            if (! $hasAuthor) {
                $this->reject(__('Приглашение больше не действует.'), 'author_membership_changed');
            }
            (new CoupleMember)->forceFill(['couple_id' => $couple->id, 'user_id' => $actor->id, 'active_user_id' => $actor->id, 'slot' => CoupleSlot::Second, 'joined_at' => now()])->save();
            $couple->forceFill(['status' => CoupleStatus::Active])->save();
            $invitation->forceFill(['status' => InvitationStatus::Accepted, 'accepted_by_id' => $actor->id, 'accepted_at' => now()])->save();

            return $couple;
        });
        Log::info('invitation.accepted', ['actor_id' => $actor->id, 'invitation_id' => $snapshot->id, 'couple_id' => $couple->id]);

        return $couple;
    }

    private function reject(string $message, string $reason): never
    {
        Log::warning('invitation.accept_rejected', ['reason' => $reason]);
        throw ValidationException::withMessages(['invitation' => $message]);
    }
}
