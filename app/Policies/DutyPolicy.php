<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CoupleStatus;
use App\Models\Duty;
use App\Models\User;

final class DutyPolicy
{
    public function view(User $actor, Duty $duty): bool
    {
        return $actor->id === $duty->executor_id || $actor->id === $duty->reviewer_id;
    }

    public function report(User $actor, Duty $duty): bool
    {
        return $actor->id === $duty->executor_id && $duty->couple->status === CoupleStatus::Active;
    }

    public function verify(User $actor, Duty $duty): bool
    {
        return $actor->id === $duty->reviewer_id && $duty->couple->status === CoupleStatus::Active;
    }
}
