<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GuestVisit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class GuestVisitPolicy
{
    public function view(User $actor, GuestVisit $item): bool
    {
        return Gate::forUser($actor)->allows('view', $item->partnerRequest);
    }
}
