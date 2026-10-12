<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reward;
use App\Models\User;
use App\Services\Catalogs\OwnedCatalogAccess;

final class RewardPolicy
{
    public function __construct(private readonly OwnedCatalogAccess $access) {}

    public function view(User $actor, Reward $item): bool
    {
        return $this->access->canView($actor, $item->user_id);
    }

    public function update(User $actor, Reward $item): bool
    {
        return $actor->id === $item->user_id;
    }
}
