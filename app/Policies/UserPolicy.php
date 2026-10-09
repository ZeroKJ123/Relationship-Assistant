<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function view(User $actor, User $user): bool
    {
        return $actor->id === $user->id;
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->id === $user->id;
    }
}
