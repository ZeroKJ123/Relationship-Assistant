<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CoupleStatus;
use App\Models\PartnerRequest;
use App\Models\User;

final class PartnerRequestPolicy
{
    public function view(User $user, PartnerRequest $request): bool
    {
        return $user->id === $request->author_id || $user->id === $request->recipient_id;
    }

    public function respond(User $user, PartnerRequest $request): bool
    {
        return $user->id === $request->recipient_id && $request->couple->status === CoupleStatus::Active;
    }

    public function acceptCondition(User $user, PartnerRequest $request): bool
    {
        return $user->id === $request->author_id && $request->couple->status === CoupleStatus::Active;
    }
}
