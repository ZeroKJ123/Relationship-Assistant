<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Enums\CoupleStatus;
use App\Enums\InvitationStatus;
use App\Models\Couple;
use App\Models\CoupleInvitation;

final class InvitationAvailability
{
    public function isAvailable(CoupleInvitation $invitation, Couple $couple): bool
    {
        return $invitation->status === InvitationStatus::Pending
            && $invitation->expires_at->isFuture()
            && $couple->status === CoupleStatus::Pending;
    }
}
