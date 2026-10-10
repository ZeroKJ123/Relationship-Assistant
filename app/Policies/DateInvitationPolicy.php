<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DateInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DateInvitationPolicy
{
    public function view(User $actor, DateInvitation $item): bool
    {
        return Gate::forUser($actor)->allows('view', $item->partnerRequest);
    }
}
