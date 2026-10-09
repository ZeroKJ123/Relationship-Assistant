<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Models\Couple;
use App\Models\User;

final class CoupleLock
{
    /** @param list<int> $userIds */
    public function lockUsers(array $userIds): void
    {
        $userIds = array_unique($userIds);
        sort($userIds);
        foreach ($userIds as $id) {
            User::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        }
    }

    public function lockCouple(int $coupleId): Couple
    {
        return Couple::query()->whereKey($coupleId)->lockForUpdate()->firstOrFail();
    }
}
