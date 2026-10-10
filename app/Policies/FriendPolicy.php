<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Friend;
use App\Models\User;

final class FriendPolicy
{
    public function view(User $actor, Friend $item): bool
    {
        return $actor->id === $item->user_id;
    }

    public function update(User $actor, Friend $item): bool
    {
        return $actor->id === $item->user_id;
    }
}
