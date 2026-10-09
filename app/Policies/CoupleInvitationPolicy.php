<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CoupleInvitation;
use App\Models\User;

final class CoupleInvitationPolicy
{
    public function cancel(User $user, CoupleInvitation $invitation): bool
    {
        return $user->id === $invitation->inviter_id;
    }
}
