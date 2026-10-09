<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CoupleStatus;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class CouplePolicy
{
    public function view(User $user, Couple $couple): bool
    {
        $allowed = $couple->members()->where('user_id', $user->id)->exists();
        if (! $allowed) {
            Log::warning('couple.access_denied', ['actor_id' => $user->id, 'couple_id' => $couple->id]);
        }

        return $allowed;
    }

    public function update(User $user, Couple $couple): bool
    {
        return $couple->status !== CoupleStatus::Ended && $couple->members()->where('user_id', $user->id)->where('active_user_id', $user->id)->exists();
    }
}
