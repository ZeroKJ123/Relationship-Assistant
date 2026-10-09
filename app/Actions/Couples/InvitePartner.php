<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Data\Couples\CoupleSetupData;
use App\Data\Couples\IssuedInvitationData;
use App\Enums\CoupleSlot;
use App\Enums\CoupleStatus;
use App\Enums\Currency;
use App\Enums\InvitationStatus;
use App\Models\Couple;
use App\Models\CoupleInvitation;
use App\Models\CoupleMember;
use App\Models\User;
use App\Services\Couples\CoupleLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InvitePartner
{
    private const TOKEN_LENGTH = 64;

    private const INVITATION_LIFETIME_DAYS = 7;

    public function __construct(private readonly CoupleLock $lock) {}

    public function execute(User $actor, CoupleSetupData $data): IssuedInvitationData
    {
        Log::debug('invitation.issue_started', ['actor_id' => $actor->id]);
        $result = DB::transaction(function () use ($actor, $data): IssuedInvitationData {
            $this->lock->lockUsers([$actor->id]);
            $couple = $this->getPendingCouple($actor, $data);
            $couple->invitations()->where('status', InvitationStatus::Pending->value)->update(['status' => InvitationStatus::Cancelled->value]);
            $token = Str::random(self::TOKEN_LENGTH);
            $invitation = new CoupleInvitation;
            $invitation->forceFill([
                'couple_id' => $couple->id, 'inviter_id' => $actor->id, 'token_hash' => hash('sha256', $token),
                'status' => InvitationStatus::Pending, 'expires_at' => now()->addDays(self::INVITATION_LIFETIME_DAYS),
            ])->save();

            return new IssuedInvitationData($couple->id, $invitation->id, $token);
        });
        Log::info('invitation.issued', ['actor_id' => $actor->id, 'invitation_id' => $result->invitationId, 'couple_id' => $result->coupleId]);

        return $result;
    }

    private function getPendingCouple(User $actor, CoupleSetupData $data): Couple
    {
        $membership = CoupleMember::query()->where('active_user_id', $actor->id)->first();
        if ($membership !== null) {
            $couple = $this->lock->lockCouple($membership->couple_id);
            if ($couple->status !== CoupleStatus::Pending) {
                throw ValidationException::withMessages(['couple' => __('У вас уже есть активная пара.')]);
            }
            Gate::forUser($actor)->authorize('update', $couple);
            $couple->fill(['started_on' => $data->startedOn, 'timezone' => $data->timezone])->save();

            return $couple;
        }
        $couple = new Couple;
        $couple->forceFill(['status' => CoupleStatus::Pending, 'started_on' => $data->startedOn, 'timezone' => $data->timezone, 'currency' => Currency::Byn])->save();
        (new CoupleMember)->forceFill(['couple_id' => $couple->id, 'user_id' => $actor->id, 'active_user_id' => $actor->id, 'slot' => CoupleSlot::First, 'joined_at' => now()])->save();

        return $couple;
    }
}
