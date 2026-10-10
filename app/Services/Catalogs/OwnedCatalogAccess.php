<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Enums\CoupleStatus;
use App\Enums\Currency;
use App\Models\CoupleMember;
use App\Models\User;

final class OwnedCatalogAccess
{
    public function getCurrency(User $actor): Currency
    {
        return CoupleMember::query()->with('couple')->where('active_user_id', $actor->id)->first()?->couple->currency ?? $actor->currency ?? Currency::Byn;
    }

    public function canView(User $actor, int $ownerId): bool
    {
        if ($actor->id === $ownerId) {
            return true;
        }
        $membership = CoupleMember::query()->where('active_user_id', $actor->id)->first();

        return $membership !== null && $membership->couple?->status === CoupleStatus::Active
         && CoupleMember::query()->where('couple_id', $membership->couple_id)->where('active_user_id', $ownerId)->exists();
    }
}
